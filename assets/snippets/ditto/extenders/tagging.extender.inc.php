<?php

/*
 * Title: Tagging
 * Purpose:
 *  	Collection of parameters, functions, and classes that expand
 *  	Ditto's functionality to include tagging
*/

// ---------------------------------------------------
// Tagging Parameters
// ---------------------------------------------------

$landing = isset($tagDocumentID) ? $tagDocumentID : $modx->documentObject['id'];
/*
	Param: tagDocumentID

	Purpose:
 	ID for tag links to point to

	Options:
	Any MODx document with a Ditto call setup to receive the tags
	
	Default:
	Current MODx Document
*/
$source = isset($tagData) ? $tagData : "";
/*
	Param: tagData

	Purpose:
 	Field to get the tags from

	Options:
	Comma separated list of MODx fields or TVs
	
	Default:
	[NULL]
*/
$caseSensitive = isset($caseSensitive) ? $caseSensitive : 0;
/*
	Param: caseSensitive

	Purpose:
 	Determine whether or not tag matching and duplicate tag removal are case sensitive

	Options:
	0 - off
	1 - on
	
	Default:
	0 - off
*/
$mode = isset($tagMode) ? $tagMode: "onlyTags";
/*
	Param: tagMode

	Purpose:
 	Filtering method to remove tags

	Options:
	onlyAllTags - show documents that have all of the tags
	onlyTags - show documents that have any of the tags
	removeAllTags - remove documents that have all of the tags
	removeTags - documents that have any of the tags
	
	Default:
	"onlyTags"
*/
$delimiter= isset($tagDelimiter) ? $tagDelimiter: " ";
/*
	Param: tagDelimiter

	Purpose:
 	Delimiter that splits each tag in the tagData source

	Options:
	Any character not included in the tags themselves
	
	Default:
	" " - space
*/
$givenTags = !empty($tags) ? trim($tags) : false;
/*
	Param: tags

	Purpose:
 	Allow the user to provide initial tags to be filtered

	Options:
	Any valid tags separated by <tagDelimiter>
	
	Default:
	[NULL]
*/

// ---------------------------------------------------
// Tagging Class
// ---------------------------------------------------
if(!class_exists("tagging")) {
	class tagging {
		var $delimiter,$source,$landing,$mode,$format,$givenTags,$caseSensitive;
	
		function tagging($delimiter,$source,$mode,$landing,$givenTags,$format,$caseSensitive) {
			$this->delimiter = $delimiter;
			$this->source = $this->parseTagData($source);
			$this->mode = $mode;
			$this->landing = $landing;
			$this->format = $format;
			$this->givenTags = $this->prepGivenTags($givenTags);
			$this->caseSensitive = $caseSensitive;
		}
	
		function prepGivenTags ($givenTags) {
			global $_GET,$dittoID;

			$getTags = !empty($_GET[$dittoID.'tags']) ? trim($_GET[$dittoID.'tags']) : false;
				// Get tags from the $_GET array

			$tags1 = array();
			$tags2= array();
		
			if ($getTags !== false) {
				$tags1 = explode($this->delimiter,$getTags);
			}
		
			if ($givenTags !== false) {
				$tags2 = explode($this->delimiter,$givenTags);		
			} 
		
			$kTags = array();
			$tags = array_merge($tags1,$tags2);
			foreach ($tags as $tag) {
				if (!empty($tag)) {				
					if ($this->caseSensitive) {
						$kTags[$tag] = $tag;
					} else {
						$kTags[strtolower($tag)] = $tag;
					}
				}
			}
			return $kTags;
		}

		function tagFilter ($value) {
			if ($this->caseSensitive == false) {
				$documentTags = array_values(array_flip($this->givenTags));
				$filterTags = array_values(array_flip($this->combineTags($this->source, $value,array(),true)));
			} else {
				$documentTags = $this->givenTags;
				$filterTags =$this->combineTags($this->source, $value,array(),true);
			}
			$compare = array_intersect($filterTags, $documentTags);
			$commonTags = count($compare);
			$totalTags = count($filterTags);
			$docTags = count($documentTags);
			$unset = 1;

			switch ($this->mode) {
				case "onlyAllTags" :
					if ($commonTags != $docTags)
						$unset = 0;
					break;
				case "removeAllTags" :
					if ($commonTags == $docTags)
						$unset = 0;
					break;
				case "onlyTags" :
					if ($commonTags > $totalTags || $commonTags == 0)
						$unset = 0;
					break;
				case "removeTags" :
					if ($commonTags <= $totalTags && $commonTags != 0)
						$unset = 0;
					break;
				}
				return $unset;
		}

		function makeLinks($resource) {
			return $this->tagLinks($this->combineTags($this->source,$resource,array(),true), $this->delimiter, $this->landing, $this->format);
		}
	
		function parseTagData($tagData,$names=array()) {
			return explode(",",$tagData);
		}

		function combineTags($tagData, $resource, $resourceTags = array(),$leaveAsArray=false) {
			$tags = array();
			foreach ($tagData as $source) {
				if(!empty($resource[$source])) {
					$tags[] = $resource[$source];
				}
			}		
			$kTags = array();
			$tags = explode($this->delimiter,implode($this->delimiter,$tags));
			foreach ($tags as $tag) {
				if (!empty($tag)) {
					if ($this->caseSensitive) {
						$kTags[$tag] = $tag;
					} else {
						$kTags[strtolower($tag)] = $tag;
					}
				}
			}
			return ($leaveAsArray == true) ? $kTags : implode($this->delimiter,$kTags);
		}

		function tagLinks($tags, $tagDelimiter, $tagID=false, $format="html") {
			global $ditto_lang;
			if(count($tags) == 0 && $format=="html") {
				return $ditto_lang['none'];
			} else if (count($tags) == 0 && ($format=="rss" || $format=="xml")) 
			{
				return "<category>".$ditto_lang['none']."</category>";
			}

			$output = "";
			foreach ($tags as $tag) {
				if ($format == "html") {
					$tagDocID = (!$tagID) ? $modx->documentObject['id'] : $tagID;
					$url = ditto::buildURL("tags=$tag",$tagDocID);
					$output .= "<a href=\"$url\" class=\"ditto_tag\" rel=\"tag\">$tag</a> ";
				} else if ($format == "rss" || $format == "xml") {
					$output .=  "
					<category>$tag</category>";
				}
			}
			return $output;
		}
	}
}

// ---------------------------------------------------
// Tagging Parameters
// ---------------------------------------------------

$tags = new tagging($delimiter,$source,$mode,$landing,$givenTags,$format,$caseSensitive);

if (count($tags->givenTags) > 0) {
	$cFilters["tagging"] = array($source,array($tags,"tagFilter")); 
		// set tagging custom filter
}

//generate TagList
$modx->setPlaceholder($dittoID."tagLinks",$tags->tagLinks($tags->givenTags, $delimiter, $landing, $format));
/*
	Placeholder: tagLinks
	
	Content:
	Nice 'n beautiful tag list with links pointing to <tagDocumentID>
*/
// set raw tags placeholder
$modx->setPlaceholder("tags",implode($delimiter,$tags->givenTags));
/*
	Placeholder: tags
	
	Content:
	Raw tags separated by <tagDelimiter>
*/
// set tagging placeholder			
$placeholders['tagLinks'] = array(array($source,"*"),array($tags,"makeLinks"));


?>