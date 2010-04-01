<?php
// Originally from http://www.onextrapixel.com/2009/06/22/how-to-add-pagination-into-list-of-records-or-wordpress-plugin/
if ( !function_exists( "findStart" ) ) {
	function findStart($limit) {
		if ((!isset($_GET['paged'])) || ($_GET['paged'] == "1")) {
	    	$start = 0;
	    	$_GET['paged'] = 1;
	    } else {
	       	$start = ($_GET['paged']-1) * $limit;
	    }
		return $start;
	}

	  /*
	   * int findPages (int count, int limit)
	   * Returns the number of pages needed based on a count and a limit
	   */
	function findPages($count, $limit) {
	     $pages = (($count % $limit) == 0) ? $count / $limit : floor($count / $limit) + 1; 

	     return $pages;
	} 

	/*
	* string pageList (int curpage, int pages)
	* Returns a list of pages in the format of "« < [pages] > »"
	**/
	function pageList($curpage, $pages, $count, $limit)
	{
		$qs = preg_replace("&p=([0-9]+)", "", $_SERVER['QUERY_STRING']);
		$start = findStart($limit);
		$end = $start + $limit;
		$page_list  = "<span class=\"displaying-num\">Displaying " . ( $start + 1 ). "&#8211;" . $end . " of " . $count . "</span>\n"; 

	    /* Print the first and previous page links if necessary */
	    if (($curpage != 1) && ($curpage)) {
	       $page_list .= "<a href=\"".$_SERVER['PHP_SELF']."?".$qs."&amp;p=1\" class=\"page-numbers\">&laquo;</a>\n";
	    } 

	    if (($curpage-1) > 0) {
	       $page_list .= "<a href=\"".$_SERVER['PHP_SELF']."?".$qs."&amp;p=".($curpage-1)."\" class=\"page-numbers\">&lt;</a>\n";
	    } 

	    /* Print the numeric page list; make the current page unlinked and bold */
	    for ($i=1; $i<=$pages; $i++) {
	    	if ($i == $curpage) {
	         	$page_list .= "<span class=\"page-numbers current\">".$i."</span>";
	        } else {
	         	$page_list .= "<a href=\"".$_SERVER['PHP_SELF']."?".$qs."&amp;p=".$i."\" class=\"page-numbers\">".$i."</a>\n";
	        }
	       	$page_list .= " ";
	      } 

	     /* Print the Next and Last page links if necessary */
	     if (($curpage+1) <= $pages) {
	       	$page_list .= "<a href=\"".$_SERVER['PHP_SELF']."?".$qs."&amp;p=".($curpage+1)."\" class=\"page-numbers\">&gt;</a>\n";
	     } 

	     if (($curpage != $pages) && ($pages != 0)) {
	       	$page_list .= "<a href=\"".$_SERVER['PHP_SELF']."?".$qs."&amp;p=".$pages."\" class=\"page-numbers\">&raquo;</a>\n";
	     }
	     $page_list .= "\n"; 

	     return $page_list;
	}

	/*
	* string nextPrev (int curpage, int pages)
	* Returns "Previous | Next" string for individual pagination (it's a word!)
	*/
	function nextPrev($curpage, $pages) {
	 $next_prev  = ""; 

		if (($curpage-1) <= 0) {
	   		$next_prev .= "Previous";
		} else {
	   		$next_prev .= "<a href=\"".$_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING']."&amp;p=".($curpage-1)."\" class='page-numbers'>Previous</a>";
		} 

	 		$next_prev .= " | "; 

	 	if (($curpage+1) > $pages) {
	   		$next_prev .= "Next";
	    } else {
	       	$next_prev .= "<a href=\"".$_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING']."&amp;p=".($curpage+1)."\" class='page-numbers'>Next</a>";
	    }
		return $next_prev;
	}
}
?>