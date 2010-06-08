<?php
/********************************************************
Name of Module: category.php
Author: Tom Stolz

Description: Search plutoz.com for terms entered.  This 
module uses the NUTCH engine to accomplish the task.
**********************************************************/
require('/var/include/main.inc');
require('/var/include/common.inc');

//require('../include/main.inc');
//require('../include/common.inc');

require('search_functions.php');
################################################################################

$sql = "SELECT id, min_val, max_val, rating_image FROM ratings ORDER BY id";
$res = mysql_query($sql);
while ($row = mysql_fetch_assoc($res)) {
  $rating['levelMax'] = $row['id'];
  $rating[$row['id']]['min'] = $row['min_val'];
  $rating[$row['id']]['max'] = $row['max_val'];
  $rating[$row['id']]['image'] = $row['rating_image'];
}

$sql = "SELECT id, min_val, max_val, rating_image FROM ratings_user ORDER BY id";
$res = mysql_query($sql);
while ($row = mysql_fetch_assoc($res)) {
  $rating_user['levelMax'] = $row['id'];
  $rating_user[$row['id']]['min'] = $row['min_val'];
  $rating_user[$row['id']]['max'] = $row['max_val'];
  $rating_user[$row['id']]['image'] = $row['rating_image'];
}

session_start(); 

$cid = $_GET['cid'];
$qry1 = "SELECT * FROM `front_page_cat` WHERE cat_id = '".$cid."' LIMIT 1";
$cat_res1	= mysql_query($qry1);
$cat_row1	= mysql_fetch_array($cat_res1);
$_GET['q']	= $cat_row1['cat_title'];

if (isset($_GET['pm']) && $_GET['pm'] == 'TRUE') {
  if (trim($_GET['q']) == "") {
     header("Location:index.php");
  }
}

//obtain page number from url, if navigating page-to-page
if (isset($_REQUEST['page'])) {
   $curr_page = $_REQUEST['page'];
} else {
   $curr_page = 1;
} 


################  CONFIGURATION SECTION  ##########################

//total number of reviews to retrieve - adjust for performance
$max_cat_returned = 300;

//specifies where the "advertorial" box will be located ... number is the records displayed from top
$adver_location = 3;

//number of reviews per page
$rev_per_page = 7;

//indicator to show how many pagination links to show for header/footer
$page_links = 10;

//number of pages max allowed.  Calc from previous values in this section
$max_pages_allowed = ceil($max_cat_returned / $rev_per_page);
###################################################################



//obtain page number from url, if navigating page-to-page - then adjust if necessary
if (!isset($_REQUEST['page'])) {
   $curr_page = 1;
} else {  
   if ($_REQUEST['page'] < 1) {
	   $curr_page = 1;	
   } else {
       $curr_page = $_REQUEST['page'];
   }
}   
if ($curr_page > $max_pages_allowed) $curr_page = $max_pages_allowed;

//echo ">>>Page Number Check1: TOTPAGES=".$_SESSION['totpages']." REQUEST PAGE=".$_REQUEST['page'];
if ($_REQUEST['page'] > $_SESSION['totpages']) {
  $curr_page = $_SESSION['totpages'];
} 
//**********************************************************************************


//obtain start record of search based on current page and reviews per page
$start_index = ($curr_page - 1) * $rev_per_page;
if ($start_index > $max_cat_returned) $start_index = $max_cat_returned;

if (!isset($_GET['q']) || trim($q) == "") {
   $q = conv_spec_chars($_SESSION['searchterms']);
}

if (isset($_GET['newsearch'])) {
   unset ($_SESSION['savesearch'],$_SESSION['savesearch_url'], $_SESSION['savelastpage'], $_SESSION['totresults'], $_SESSION['totpages']);
}

############################################################################################################################
#                                        NUTCH DATA RETRIEVAL SECTION                                                      #
############################################################################################################################    
$xml_parser = xml_parser_create();
$rss_parser = new RSSParser();
xml_set_object($xml_parser,&$rss_parser);
xml_set_element_handler($xml_parser, "startElement", "saveRecord");
xml_set_character_data_handler($xml_parser, "characterData");
  
$start_time_opensearch = microtime_float(); 
         
//Call to OPENSEARCH to get RESULTS! Speedy and Efficient, only gets page of info, does not store every result!
//Use this section to point to the OpenSearch source...
       
$filedesc = "http://10.0.0.100:9080/nutch-1.0-dev/opensearch?lang=en&query=".conv_spec_chars(urlencode($_GET['q']))."&hitsPerSite=1&hitsPerPage=".$rev_per_page."&start=".$start_index;

//echo "INITIAL FILEDESC=".$filedesc."<br>";
	     
if (!(@ $fp = fopen($filedesc,"r"))) {   
    $found = 0;
    $loop_start = ($curr_page - 1) * $rev_per_page;
//echo "CURR_PAGE = ".$curr_page." TOTPAGES = ".$_SESSION['totresults']."<br>";	
//echo "<br>************<br>";	
	while (!$found) {
		   //reached the end of data...find the last page to display	
//echo "LOOPSTART = ".$loop_start."<br>";		   
	   $filedesc = "http://10.0.0.100:9080/nutch-1.0-dev/opensearch?lang=en&query=".conv_spec_chars(urlencode($_GET['q']))."&hitsPerSite=1&hitsPerPage=1&start=".$loop_start; 
//echo $filedesc."<br>************<br>";		   
	   if ((@ $fp = fopen($filedesc,"r"))) {
	      $found = 1;
	   } else {
	      $loop_start = $loop_start - $rev_per_page;
	   }		   
	}
		
	$_SESSION['totresults'] = $loop_start + 1;
	$_SESSION['totpages'] = ceil($loop_start / $rev_per_page) + 1;
//echo "TOTRESULTS=".$_SESSION['totresults']." TOTPAGES=".$_SESSION['totpages']."<br>";
	$curr_page = $_SESSION['totpages'];
	$filedesc = "http://10.0.0.100:9080/nutch-1.0-dev/opensearch?lang=en&query=".conv_spec_chars(urlencode($_GET['q']))."&hitsPerSite=1&hitsPerPage=".$rev_per_page."&start=".$loop_start; 
    $fp = fopen($filedesc,"r");
//echo "FILE=".$filedesc;
		
} else {
    $_SESSION['savelastpage'] = $curr_page;
}
		 
$end_time_opensearch = microtime_float();

$rows = 0;
$curr = 0;
$start_time_xml = microtime_float(); 
$url_list = array();
$url_full_list = array();
$data = @fread($fp, 4096);

if (isset($_GET['newsearch'])) {    
   $tot_results = substr($data, strpos($data,'<opensearch:totalResults>')+25, strpos($data,'</opensearch:totalResults>')-strpos($data,'<opensearch:totalResults>')-25);  
   if ($tot_results <= $max_cat_returned) {
     $_SESSION['totresults'] = $tot_results;
   } else {
     $_SESSION['totresults'] = $max_cat_returned;			 
   }
   $_SESSION['totpages'] = ceil($_SESSION['totresults'] / $rev_per_page);
}
	     
while ($data) {
   xml_parse($xml_parser, $data, feof($fp))
   	or die(sprintf("XML error: %s at line %d", 
   xml_error_string(xml_get_error_code($xml_parser)), 
   xml_get_current_line_number($xml_parser)));   
			
   $data = @fread($fp, 4096);
      
}

@fclose($fp);

xml_parser_free($xml_parser);
	   
$end_time_xml = microtime_float(); 
   
$num_results_page = count($results_full_url);
if ($num_results_page == 0) $tot_results = 0;
   $tot_results = count($hash_list);	  	  
	   
   $_SESSION['savesearch'] = $hash_list;
   $_SESSION['savesearch_url'] = $results_full_url;
       
   if ($curr == ($rev_per_page - 1)) {
        $max_disp_page = $rev_per_page;
   } else {
	    $max_disp_page = $curr;
   }
   $max_in_page = count($hash_list);

##############################################################################################################

$entered_string = conv_spec_chars($_GET['q']);
$entered_string = stripslashes($entered_string);
$entered_string = str_replace("\"","'", $entered_string);
$allwords = preg_split('/[\"W]+?/',$entered_string, PREG_SPLIT_NO_EMPTY);

$allwordsdisp = "";
foreach ($allwords as $a_c) {   
   $allwordsdisp .= $a_c;
}

$pagewordsdisp = str_replace(" ", "+", $allwordsdisp);

######################################################################################
#########################  PAGING LOGIC - BEGIN  #####################################

$results_hash = $_SESSION['savesearch'];
$results_url_full = $_SESSION['savesearch_url'];

$tot_results = count($results_hash);
$tot_pages = $_SESSION['totpages'];

//echo "CURR_PAGE=".$curr_page." TOT_RESULTS=".$tot_results." TOT_PAGES=".$tot_pages;

if ($curr_page < 1 || $curr_page > $tot_pages) {
   $curr_page = 1;
}

$next_ind = 0;
$prev_ind = 0;
$page_header = "";
$page_footer = "";

if ($curr_page == 1) {
   $start_link = 0;
}
else {
   $start_link = ($curr_page - 1) * $rev_per_page;
}
$end_link = ($curr_page * $rev_per_page) - 1;
$page_groups = ceil($tot_pages/$page_links);
$page_break = array();
$page_break[1] = 1;
for ($i = 2; $i <= $page_groups; $i++) {
   $page_break[$i] = (($i - 1) * $page_links) + 1;
}

$calc = ceil($curr_page/$page_links);
$start_page = $page_break[$calc];

if ($page_groups == 1) {
   $end_page = $page_links;
}
elseif ($curr_page >= $page_break[$page_groups]){
   $end_page = $tot_pages;
}
else {
   $end_page = $page_break[$calc + 1] - 1;
}

if ($calc == 1 and $page_groups > 1) {
   $prev_ind = 0;
   $next_ind = 1;
}
elseif ($calc > 0 && $calc < $page_groups) {
   $prev_ind = 1;
   $next_ind = 1;
}
elseif ($calc == $page_groups && $page_groups > 1) {
   $prev_ind = 1;
   $next_ind = 0;
}

$page_display = $err_msg = "";
//if ($tot_results == 0) {
if ($_SESSION['totresults'] == 0) { 
   $err_msg = "<center>Sorry.  No Results Found. </center><br>";   
}

if ($prev_ind == 1) {
   
          $page_display .= "<a href=\"category.php?cid=".$cid."&page=".($start_page - 1)."\" class=\"pageLinks\"><<</a>&nbsp;&nbsp;";
   
}
if ($curr_page > 1) {
          
		  $page_display .= "<a href=\"category.php?cid=".$cid."&page=".($curr_page - 1)."\" class=\"pageLinks\">prev</a>&nbsp;&nbsp;";
   
}

for ($i = $start_page; $i <= $end_page; $i++) {
   if ($i > $tot_pages) {
      break;
   }
   if ($i == $curr_page) {      
      $page_display .= "<span class=\"pageCurr\">".$i."</span>&nbsp;&nbsp;";
   }
   else {
      $page_display .= "<a href=\"category.php?cid=".$cid."&page=".$i."\" class=\"pageLinks\">".$i."</a>&nbsp;&nbsp;";
   }

}

if ($curr_page < $tot_pages) {
   
       $page_display .= "<a href=\"category.php?cid=".$cid."&page=".($curr_page + 1)."\" class=\"pageLinks\">next</a>&nbsp;&nbsp;";
   
}

if ($next_ind == 1) {
   
      $page_display .= "<a href=\"category.php?cid=".$cid."&catid=".$cat_search."&page=".($end_page + 1)."\" class=\"pageLinks\">>></a>&nbsp;&nbsp;";
   
}


#########################  PAGING LOGIC - END   ######################################
######################################################################################
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link rel="icon" href="<?php echo $imageLib?>plutoz.ico" type="image/x-icon" />
<link rel="shortcut icon" href="<?php echo $imageLib?>plutoz.ico" type="image/x-icon" />
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<TITLE>Plutoz Search for <?php echo $_GET['q']; ?></TITLE>
<link href="<?php echo $cssLib."results.css"; ?>" type="text/css" rel="stylesheet" />
<script language="javascript" type="text/javascript" src="<?php echo $resourcesLib; ?>js/ajax_functions.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $resourcesLib; ?>js/javascript.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $resourcesLib; ?>js/prototype.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $resourcesLib; ?>js/login.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $resourcesLib; ?>js/dropdown.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $resourcesLib; ?>js/switchcontent.js" ></script>
<script language="javascript" type="text/javascript" src="<?php echo $infoLib; ?>ajax.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $infoLib; ?>ajax-tooltip.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $infoLib; ?>ajax-dynamic-content.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $resourcesLib; ?>js/popup/fancyzoom.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $resourcesLib; ?>js/popup/fancyzoomhtml.js"></script>
<script src="resources/facefiles/jquery-1.2.2.pack.js" type="text/javascript"></script>
<link href="resources/facefiles/facebox.css" media="screen" rel="stylesheet" type="text/css" />
<script src="resources/facefiles/facebox.js" type="text/javascript"></script>
<script language="JavaScript" type="text/JavaScript">
function UpdateResults() {
   var selObj = document.getElementById('limit');
   var selIndex = selObj.selectedIndex;
   document.location.href='category.php?cid='+'<?php echo conv_spec_chars(urlencode($_GET['q'])); ?>'+'&limit='+selObj.options[selIndex].text;
}
</script>
<script type="text/javascript">
    jQuery(document).ready(function($) {
      $('a[rel*=facebox]').facebox() 
    })
</script>

<style type="text/css">

.thickstyle{
background: silver;
}
</style>

<style type="text/css">
.balloonstyle{
position:absolute;
top: -500px;
left: 0;
padding: 5px;
visibility: hidden;
border:1px solid black;
font:normal 11px Verdana;
line-height: 18px;
z-index: 100;
background-color: #FF9933;
width: 200px;
/*Remove below line to remove shadow. Below line should always appear last within this CSS*/
filter: progid:DXImageTransform.Microsoft.Shadow(color=gray,direction=135,Strength=5);
}
.handcursor{
cursor:hand;
cursor:pointer;
}

#ajax_tooltipObj{
	z-index:1000000;
	text-align:left;
}
#ajax_tooltipObj div{
	position:relative;
}

/* If you don't want the arrow - Just set the width of this div to 1 or something like that and drop the background selectors from the CSS below */

#ajax_tooltipObj .ajax_tooltip_arrow{	/* Left div for the small arrow */
	width:20px;
	position:absolute;
	left:0px;
	top:0px;
	background-repeat:no-repeat;
	background-position:center left;
	z-index:1000005;
	height:60px;
}

#ajax_tooltipObj .ajax_tooltip_content{
	/*border:1px solid #317082;	 /*Border width */
	left:10px;	/* Same as border thickness */
	position:absolute;
	top:0px;
	bottom:0px;
	/*width:290px;*/	/* Width of tooltip content */
	height:350px;	/* Height of tooltip content */
	/*background-color: #FF9900;	/* Background color */
	padding:5px;	/* Space between border and content */
	font-size:0.8em;	/* Font size of content */
	overflow:auto;	/* Hide overflow content */
	z-index:1000001;
	color: #000000;
}
img, #bg1 { behavior: url(info/iepngfix/iepngfix.htc) }
img, #bg2 { behavior: url(info/iepngfix/iepngfix.htc) }
img, #bg3 { behavior: url(info/iepngfix/iepngfix.htc) }
#Layer1 {
	position:absolute;
	width:200px;
	height:115px;
	z-index:1;
}
#Layer2 {
	position:absolute;
	width:91px;
	height:115px;
	z-index:1;
	top: 15px;
	left: 7px;
}
#Layer3 {
	position:absolute;
	width:200px;
	height:115px;
	z-index:1;
}
</style>
</head>

<body onLoad="setupZoom();">

<?php 
###########################################    
###  Display Top Area - Logo/Search Box ### 
########################################### 
?>
<table cellpadding="0" cellspacing="0" width="100%" bgcolor="#FFFFFF" border="0">
	<tr>
		<td>  
		  <?php
		  ###########################
		  ###  HEADER ###############
		  ###########################
		  require('header.php');
		  ?>
		</td>
	</tr>
	<tr><td><img src="resources/images/imagesbeta/spacer.gif" width="1" height="16"></td></tr>
	<tr>
		<td align="left">
			<table border="0" cellpadding="0" cellspacing="0" width="1003">
				<tr>
					<td><img src="resources/images/imagesbeta/spacer.gif" width="27" height="1"></td>
					<td><a href="index.php"><img src="resources/images/imagesbeta/plutocategorysearchlogo.jpg" border="0"/></a></td>
					<!-- td><img src="resources/images/imagesbeta/spacer.gif"  width="40" height="1"></td -->
					<form action="search.php" method="GET">
					<td class="searchboxadj">
						 <table cellpadding="0" cellspacing="0" style="width:536px; height:59px;">
							<tr>
								<td valign="top"><img src="resources/images/imagesbeta/searchbox_left.gif"/></td>
								<td background="resources/images/imagesbeta/searchbox_section.gif" align="center" >
									<input name="q" maxlength="200" type="text" class="searchbox" value="" />			               
								</td>
								<td>
									<input class="searchbutton" type="image" src="resources/images/imagesbeta/searchbox_button.gif"/>
									<input type="hidden" name="newsearch" value="TRUE" />  
								</td>
							</tr>
						</table>
					</td>
					</form>
					<!-- td><img src="resources/images/imagesbeta/spacer.gif" width="35" height="1"></td -->
					<td align="right">
						<table cellpadding="0" cellspacing="0" border="0">
							<tr>
								<td align="right"><img src="resources/images/imagesbeta/category_left.jpg" border="0"></td>
								<td>
									<table border="0" cellpadding="0" cellspacing="0" background="resources/images/imagesbeta/category_center.jpg" height="50" width="96">
										<tr><td class="headerfooterLinks"><font color="#ffffff">Select a Category</font><td></tr>
										<tr>
											<td align="center">
												<a href="#" class="headerfooterLinks"><font color="#EE2D24">
													<select><?php //obtain category list
														$category = "SELECT * FROM front_page_cat";
														$categorylist = mysql_query($category);
														while ($cat_list = mysql_fetch_array($categorylist)) {
															$_catlist	= $cat_list['cat_title'];
															if( $_catlist == $_GET['q'])
																echo '<option name="' . $_catlist . '" value="' . $_catlist . '" SELECTED>' . $_catlist . '</option>';
															else
																echo '<option name="' . $_catlist . '" value="' . $_catlist . '">' . $_catlist . '</option>';
															echo "<br>";
														} ?>
													</select>
												</font></a>
											</td>
										</tr>
										<tr><td><img src="resources/images/imagesbeta/spacer.gif"  width="1" height="5"></td></tr>
									</table>
								</td>											
								<td align="right"><img src="resources/images/imagesbeta/category_right.jpg" border="0"></td>  
							</tr>	
						</table>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr><td><img src="resources/images/imagesbeta/spacer.gif"  width="1" height="10"></td></tr>
	<tr>
		<td>
			<table cellpadding="0" cellspacing="0" width="1003">  <!-- Main Table -->  
				<tr valign="top">
					<td width="209px">
						<table border="0" cellpadding="0" cellspacing="0">
							<tr><td colspan="2"><img src="resources/images/imagesbeta/spacer.gif" width="1" height="9"></td></tr>
							<tr><td colspan="2"><img src="resources/images/imagesbeta/<?php echo $_GET['q']; ?>_title.jpg" border="0" alt=""></td></tr>
							<tr><td colspan="2"><img src="resources/images/imagesbeta/spacer.gif" width="1" height="11"></td></tr>
							<tr>
								<td><img src="resources/images/imagesbeta/spacer.gif" width="21" height="1"></td>
								<td>
									<table border="0" cellpadding="0" cellspacing="0">
										<tr><td><img src="resources/images/imagesbeta/title_image_top_add.jpg"></td></tr>
										<tr>
											<td>
												<table border="0" cellpadding="0" cellspacing="0" width="178" background="resources/images/imagesbeta/title_image_middle.jpg">
													<tr><td colspan="3"><img src="resources/images/imagesbeta/spacer.gif" width="1" height="10"></td></tr>
													<tr>
														<td><img src="resources/images/imagesbeta/spacer.gif" width="14" height="1"></td>
														<td>
															<?php
																//Get Category Name
																$query = "SELECT * FROM contribute_content WHERE category='".$cid."' AND ((CURDATE( ) >= date_start) AND (CURDATE( ) <= date_expire)) AND approved=1 AND reject=0 ORDER BY RAND( ) LIMIT 7"; 
																$qadv = mysql_query($query);			   
																$qnumadv = @mysql_num_rows($qadv);
																$row = mysql_fetch_array($qadv);
															?>
															<span style="color:#D00800; vertical-align:text-top; font-size:13px;"><b>Hot <?php echo $allwordsdisp; ?>... Hot Deals!</b></span>
															<?php
															  $qadv1 = mysql_query($query);
															  while($rowadv = mysql_fetch_array($qadv1)) {
															  $insert_id_Arr[] 	= $rowadv['con_id']; // To avoid duplicate insert content in the same page.
															  $advid		= $rowadv['con_id'];
															  $advtitle 	= $rowadv['title'];
															  $advcontent 	= $rowadv['content'];
															  echo '<br><img  src="resources/images/imagesbeta/button.jpg">';
															  echo "<a href=\"category_popup.php?cid=$advid\" rel=\"facebox[.thickstyle]\">$advtitle</a>";
															  echo "<br>";
															  }
															?>
														</td>
														<td><img src="resources/images/imagesbeta/spacer.gif" width="24" height="1"></td>
													</tr>
												</table>
											</td>
										</tr>
										<tr><td><img src="resources/images/imagesbeta/title_image_bottom.jpg"></td></tr>
									</table>
								</td>
							</tr>											
							<tr><td colspan="2"><img src="resources/images/imagesbeta/spacer.gif"  width="1" height="11"></td></tr>
							<tr><td colspan="2"><img src="resources/images/imagesbeta/<?php echo $_GET['q']; ?>_img.jpg" alt="" border="0"></td></tr>
						</table>
					</td>
					<td width="794px">
		<table cellpadding="0" cellspacing="0" border="0" width="794px">
			<tr>
				<td><img src="resources/images/imagesbeta/left_top_img.jpg" width="565px" height="12px"></td>
				<td><img src="resources/images/imagesbeta/right_top.png" width="229px" height="12px"></td>
			</tr>
			<tr style="background-image:url(resources/images/imagesbeta/body_center.jpg); background-repeat:repeat-y;"  width="794px" valign='top'>
				<td width="565px">
				<table cellpadding="0" cellspacing="0" border="0" width="564px" align="center"> <!-- Left Table -->
					<tr><td><img src="resources/images/imagesbeta/spacer.gif"  width="1" height="10"></td></tr>
					<tr><td><img src="resources/images/imagesbeta/spacer.gif" width="20" height="1"><img src="resources/images/imagesbeta/<?php echo $_GET['q']; ?>_heading.jpg" alt=""></td></tr>
					<tr><td><img src="resources/images/imagesbeta/spacer.gif" width="1" height="10"></td></tr>
					<tr><td width="1" height="1" bgcolor="#939393"><img src="resources/images/imagesbeta/spacer.gif"></td></tr>
					<tr>
						<td> <!-- Left Side Inner Table -->
						<table cellpadding="0" cellspacing="0" border="0" align="center" width="564px">
						<tr valign="top"><!-- Hold for Search Results -->
							<td>
<?php ##################################################################################################### ?>
							  <div align="center">  <!-- contentColumn -->  


<?php
$count_page = 0;
$insert_count = 1;
$insert_id_Arr = array();

//echo "TOTRESULTS2=".$_SESSION['totresults']." REVPERPAGE=".$rev_per_page." MAXDISPPAGE=".$max_disp_page;

$start_time_display = microtime_float(); 

if ($tot_results > 0) {
   for ($i = 0; $i < $rev_per_page && $i < $max_in_page && $i < $max_disp_page; $i++) {
      $count_page++;
      $curr_search_num = $i + 1;         
	  $d_review_url_full = $results_url_full[$i];      
      $save_domain = strtolower(parse_url($d_review_url_full, PHP_URL_HOST));  
      $d_review_url = "http://".$save_domain;
      if (substr($save_domain, 0, 3) == "www") {
	   $d_screenshot = substr(strtolower(parse_url($d_review_url_full, PHP_URL_HOST)), 4).".jpg";
	  } else {
	    $d_screenshot = strtolower(parse_url($d_review_url_full, PHP_URL_HOST)).".jpg";
	  }
      if(!file_exists($screenshotLib.$d_screenshot)) {
           $d_screenshot = "image_coming_soon.jpg";
      }
	  //$hash_search = $results_hash[$i];
	  $hash_search = $hash_list[$i];
	  	       	   
	  $query = "SELECT review_title, review_content, review_url, overall_rating, LOWER(screenshot), user_rating, date_format(last_updated_date, '%M-%d-%Y'), review_id, doc_hash FROM reviews WHERE doc_hash = '".$hash_search."'";

      //echo $query;             
	 
      $qret = mysql_query($query);
      $row = mysql_fetch_row($qret);
      $qnum_reviewfound = @mysql_num_rows($qret);
	  
	  if ($qnum_reviewfound > 0) {
        $d_review_id = $row[7];
        $d_review_title = $row[0];
      } else {
	    $d_review_id = 0;
        $d_review_title = $d_review_url;
	  }

        
	  $prefix = "";
      $addprefix = false;
	  
	
      foreach ($allwords as $awtemp) {
         $pos = stripos($d_review_title, $awtemp);
         if ($pos === false) {
            $prefix .= ucwords($awtemp)." ";
            $addprefix = true;
         }
      }
	  
      if ($addprefix == true) $d_review_title = $prefix ." - ".$d_review_title;

      if ($qnum_reviewfound > 0) {
         $d_review_content = $row[1];
         $d_review_url_disp = substr($d_review_url_full, 0, 60);
         if (strlen($d_review_url_full) > 60) {
            $d_review_url_disp .= "...";
         }
         $d_review_url_short = parse_url($row[2], PHP_URL_HOST);      
         $d_overall_rating = $row[3];
         $d_user_rating = $row[5];
	  } else {
	     $d_review_content = "<b>Review Coming Soon!</b> <br><br>".$site_desc[$i];
		 $d_review_url_disp = "";
		 $d_screenshot = "image_coming_soon.jpg";	 
	  }

      $rx = $d_overall_rating;
      $ux = number_format(($d_user_rating), 0);

      $levelx = 0;
      for($lx=1; $lx<=$rating['levelMax']; $lx++)
          {
             ##   if($rx >= $rmin[$lx] && $rx <= $rmax[$lx])
		  if($rx >= $rating[$lx]['min'] && $rx <= $rating[$lx]['max'])
                {
                        $levelx = $lx;
                        if($rx == 0){
                            $lx=0;
                        }
                        break;
                }
          }
      $rImg= $rating[$lx]['image'];

	  //get number of ratings for this site
	  $sql = "SELECT * FROM site_ratings WHERE site_id = $d_review_id AND Review_Flag=1";
	  $result = @mysql_query($sql);
	  $rating_count = mysql_num_rows($result);
	  $tally_ratings = 0;
	  while ($row = mysql_fetch_array($result)) {
		$tally_ratings = $tally_ratings + $row['rating'];
	  }
	
	  if ($tally_ratings == 0 || $rating_count == 0) {
		 $user_num_rating = 0;
	  } else {
		 $user_num_rating = $tally_ratings / $rating_count;
	  }	
		
      switch ($user_num_rating)
	  {
	  	case 0:
			$lx = 0;
			break;
		case 1:
			$lx = 2;
			break;
		case 2:
			$lx = 4;
			break;
		case 3:
			$lx = 6;
			break;
		case 4:
			$lx = 8;
			break;
		case 5:
			$lx = 10;
			break;
	  }
	  
	  if ($user_num_rating != 0 && ($user_num_rating > 0 && $user_num_rating < 1.5)) { $lx = 2; }
	  if ($user_num_rating > 2.0 && $user_num_rating < 2.5) { $lx = 3; }
	  if ($user_num_rating >= 1.5 && $user_num_rating < 2) { $lx = 3; }
	  if ($user_num_rating > 3.0 && $user_num_rating < 3.5) { $lx = 5; }
	  if ($user_num_rating >= 2.5 && $user_num_rating < 3) { $lx = 5; }
	  if ($user_num_rating > 3.0 && $user_num_rating < 3.5) { $lx = 7; }
	  if ($user_num_rating >= 3.5 && $user_num_rating < 4) { $lx = 7; }
	  if ($user_num_rating > 4.0 && $user_num_rating < 4.5) { $lx = 9; }
	  if ($user_num_rating >= 4.5 && $user_num_rating < 5) { $lx = 9; }
	  
      $uImg= $rating_user[$lx]['image'];

      $w = "30";
      $h = "30";

      $query1 = "SELECT * FROM comments WHERE comment_review_id='$d_review_id' AND comment_type=0 AND approved_flag=1";
      $result1 = mysql_query($query1);
      $num_comments = mysql_num_rows($result1);
      
	  if ($num_comments == 1) {
	     $review_word = "Review";
      } else {
	     $review_word = "Reviews";
      }
	        
?>
      
             <table>
                <tr>
                 <td valign="top">        
                     <div class="screenshot1">
                     <a href="<?php echo $d_review_url_full; ?>" target="_blank" >
					    <img src="<?php echo $screenshotLib.$d_screenshot ?>" border="0" width="200" height="120">
				     </a>
                     </div>
                 </td>
                 <td width="364" valign="top">
                    
                        <div class="titlereview"><a href="<?php echo stripslashes($d_review_url_full) ?>" target="_blank" rel=nofollow class="titleLinks"><?php echo $d_review_title ?></a></div>
						<div class="review">
						<?php echo stripslashes($d_review_content) ?>
						<?php if ($qnum_reviewfound > 0) { ?>
						 
						&nbsp;&nbsp;<span class="URL"><a href="<?php echo $d_review_url ?>" target="_blank" rel=nofollow class="URL"><?php echo $d_review_url_short ?></a></span></div>
                        <?php } ?>
						<div class="ratingsection">
						    <table cellpadding="0" cellspacing="0" width="364">
							  <tr>
							    <td class="plutozrating" height="20">Plutoz Rating:</td>
								<td>
								<?php if ($qnum_reviewfound > 0) { ?>
								<img width=87 src="./resources<?php echo $rImg?>" >
								<?php } else {?>
								<img width=87 src="./resources/images/stars/na1.gif" >        
								<?php } ?>
								</td>
								<td>
									<font size="-1">
									<?php if ($qnum_reviewfound > 0) { ?>
									   <a href="reviews.php?review_id=<?php echo $d_review_id ?>" class="reviewLinks">
									   <!--<span class="reviewLinks" style="color: #FF8000;">-->
									   <?php echo $num_comments."&nbsp;&nbsp;".$review_word; ?>
									   <!--</span>-->
									   </a>
									   &nbsp;&nbsp;|&nbsp;&nbsp;
									   <a href="reviews.php?review_id=<?php echo $d_review_id ?>"  class="reviewLinks">
									   <!--<span class="reviewLinks" style="color: #FF8000;">-->
									   Write Reviews
									   <!--</span>-->
									   </a>	
									<?php } ?>
									</font>
								</td>
							  </tr>
							  
							  <tr>
							    <td align="left" class="userrating" height="20">User Rating:</td><td align="left">
								<?php if ($qnum_reviewfound > 0) { ?>
								<img width=87 src="./resources<?php echo $uImg?>" >
								<?php } else {?>
								<img width=87 src="./resources/images/stars/na1.gif" >        
								<?php } ?>
								</td>
								<td>&nbsp;</td>
							  </tr>
							</table>
                            
						</div>
			     </td>
               </tr>
            </table>
			     
			   	   			   
<?php    ###############  contribute_content Insert - BEGIN  ###############
         $scramble_query = array();
         //if ($count_page == $adver_location) {
          if ($tot_results > 3  && ($count_page == 2 || $count_page == 4)) {
           	for($temp_I=0; $temp_I < 2; $temp_I++){//Execute for two times, show two 'inserts'
			    $scramble_query = $allwords;			
	            shuffle($scramble_query);
				//print_r($scramble_query);
				$numquery = count($scramble_query);	
				$found_adv = 0;			
				for ($adindex = 0; $adindex < $numquery && $found_adv == 0; $adindex++) {
				   $curr_element_keyword = $scramble_query[$adindex];
				   //To avoid duplicate 'insert' content in same page
				   if(count($insert_id_Arr)){
				    	$insert_ids = implode("','",$insert_id_Arr);
						$insert_ids = " AND con_id NOT IN ('".$insert_ids."') ";
				   }else{
						$insert_ids = '';
				   }
				   //----G $query = "select * from contribute_content where keywords like '%".$curr_element_keyword."%' and approved = 1 ".$insert_ids."ORDER BY RAND()";   
				   //----G New query for multiple keyword columns(5)

				   //Get Category Name
				   
				   $query = "SELECT * FROM contribute_content WHERE category='".$cid."' AND ((CURDATE( ) >= date_start) AND (CURDATE( ) <= date_expire)) AND approved=1 AND reject=0 ".$insert_ids." ORDER BY RAND( )";   
				
				   $qadv = mysql_query($query);			   
				   $qnumadv = @mysql_num_rows($qadv);
				   if ($qnumadv > 0) {
				      $found_adv = 1;
					  $rowadv = mysql_fetch_array($qadv);
					  $insert_id_Arr[] 	= $rowadv['con_id']; // To avoid duplicate insert content in the same page.
					  $advtitle 		= $rowadv['title'];
					  $advcontent 		= $rowadv['content'];
					  $img_name			= $rowadv['img_name'];
				}
			 }   
				

	            if ($found_adv == 1) {			       
				       echo "<br><div align='center'><table width='505' cellpadding='0' cellspacing='0'>";
				       echo "<tr><td style='background-image:url(resources/images/new_screens/advertorial_top.gif);background-repeat:no-repeat;'>&nbsp;</td></tr>";
				       echo "<tr><td align='center'>"; 
				       echo "   <table cellspacing='0' cellpadding='0'>";
				       echo "     <tr ><td width='400' align='left' style='padding-top:0px;'>";
					   echo "          <div id='expand".$insert_count."-title' class='handcursor' >"; 
					   echo "            <span style='font-family: Arial, Helvetica, sans-serif; font-size:14px; color:#007DFB;'><b>".$advtitle."</b></span>";
					   echo "          </div>";
					   echo "          <div id='expand".$insert_count."' class='advicecontent'>";
					   echo "          <br><div style='margin-top:10px; border-top:2px solid #CCCCCC; width:680;'>&nbsp;</div>";
					   echo "<img src='contribute_images/".$img_name."' height='100' width='150' style='border: 3px solid #CECECE; float: left; margin-right: 10px;'>";
	                   echo $rowadv['content']."</div>";
	                   echo "          </td>";
					   echo "     </tr>";
					   echo "   </table>";
					   echo "</td></tr>";
					   echo "<tr><td style='background-image:url(resources/images/new_screens/advertorial_bottom.gif);height:22pxbackground-repeat:no-repeat;'>&nbsp;</td></tr>";
				       echo "</table></div><br>";
	?>				   
	
	<?php			
	             } else {  
	                   echo "<div align=\"center\" class=\"break1\">";
	                   echo "<img src=\"resources/images/new_screens/separator1.gif\" />";
				       echo "</div>";
				       break;// Exit from the inner loop $temp_I
	             }
	             $insert_count++;// This is to SwichContent script for grouping or switching more than one text section
             }
          } else {  
             echo "<div align=\"center\" class=\"break1\">";
             echo "<img src=\"resources/images/new_screens/separator1.gif\" />";
			 echo "</div>";
          }  //if ($count_page == $adver_location)  
			
          ###############  contribute_content Insert - END  ###############
          
   }  //for
?>   
				   <script type="text/javascript">
                   var expandcontent = new switchcontent("advicecontent", "div") //Limit scanning of switch contents to just \"div\" elements";
                   expandcontent.setStatus('<div style="float:right;margin:1px;"><img src="<?php echo $imageLib?>/new_screens/advertorial_close.gif" /></div> ', '<div style="float:right;margin:1px;"><img src="<?php echo $imageLib ?>/new_screens/advertorial_open.gif" /></div> ')
                   expandcontent.setColor('darkred', 'black')
                   expandcontent.setPersist(false)
                   expandcontent.collapsePrevious(true) //Only one content open at any given time
                   expandcontent.init()
                   expandcontent.sweepToggle('contract')
                   </script>	
   
<?php   
}  //if ($tot_results == 0)

$end_time_display = microtime_float(); 

$time_display = round($end_time_display - $start_time_display, 3);
$time_xml = round($end_time_xml - $start_time_xml, 3);
$time_opensearch = round($end_time_opensearch - $start_time_opensearch, 3);

//echo "<center>Completed OpenSearch in $time_opensearch seconds.  Completed XML Parsing in $time_xml  seconds.  Completed Display in $time_display seconds.</center><br><br> "; 
 
echo $str1;
echo $err_msg;
echo "<div align=\"center\">";
echo "<span class=\"pageLinksTitle\">Pages :&nbsp;&nbsp;</span>".$page_display;
echo "</div>";
echo "<br>";
?>

<!--
           <div align="center" style="margin-top:5px">
                <span>Results per page:</span>
                   <SELECT NAME="limit" id="limit" style="border:1px #000000 solid; font-size:10px;" onchange=UpdateResults()>
                      <OPTION VALUE="7" <?php if ($rev_per_page==7) echo 'SELECTED';?>>7
                      <OPTION VALUE="10" <?php if ($rev_per_page==10) echo 'SELECTED';?>>10
                      <OPTION VALUE="20" <?php if ($rev_per_page==20) echo 'SELECTED';?>>20
                      <OPTION VALUE="50" <?php if ($rev_per_page==50) echo 'SELECTED';?>>50
                   </SELECT>               

           </div>
-->
		   
         </div><!-- contentColumn -->	
<?php ##################################################################################################### ?>
							</td>
						</tr>
						</table>
						</td>
					</tr>
				</table> <!-- Left Table End -->
			</td>
			<td valign="top" style="background-image:url(resources/images/imagesbeta/body_center_bg_229.jpg); background-repeat:repeat-y;"><!-- Right Side Table -->
				<table cellpadding="0" cellspacing="0" border="0" align="center" width="229px">
				<tr>
					<td>
<?php #################################################################################################### ?>
	<?php
//----Feedback: Processing feedback form
if($_POST['feedback_submitted'] == 1)
{
  $accountid = session_id();
  $feedback  = $_POST['feedback'];
  $comments = trim(escape_data($_POST['comments']));
  $dof = date('Y-m-d');
  $queryused = conv_spec_chars($_GET['q']);
  
  $session_id = session_id();
  $qry = "SELECT * FROM feedback WHERE User_ID = '$session_id'";
  $qret = mysql_query($qry);
  
  if (mysql_num_rows($qret) <= 25 && $feedback != "") {
     $qry = "INSERT INTO feedback(User_ID, Feedback, QueryUsed, Comments, dof) values('$accountid', '$feedback', '$queryused', '$comments','$dof')";
     mysql_query($qry);
	 $msg = 'Thanks for your Feedback!';
  } else {
     $msg = 'Please Select Yes or No';
  }
  
}
?>

		
<?php
###################################    
###   Display Banner Ad         ### 
###################################
echo "<div class=\"logo\" style=\"padding-right:20px; padding-left:20px; align=\"center\">";

if ($tot_pages) {



   //<!--/* OpenX Local Mode Tag v2.6.3 */-->

  // The MAX_PATH below should point to the base of your OpenX installation
  //define('MAX_PATH', 'C:\apache2.2\htdocs\plutoz.com\openx');
  define('MAX_PATH', '/var/www/plutoz.com/openx');
  if (@include_once(MAX_PATH . '/www/delivery/alocal.php')) {
    
    if (!isset($phpAds_context)) {
	
      $phpAds_context = array();
    }
  
    //if user supplies multiple terms, shuffle the entries to allow equal chance at ads appearing for each keyword.
    shuffle($allwordstemp);

    foreach ($allwordstemp as $awtemp) {
      $awtemp = '+img,+'.$awtemp;
      $phpAds_raw = view_local($awtemp, 0, 0, 0, '', '', '0', $phpAds_context);
      //$phpAds_raw = view_local('+img,+airlines', 0, 0, 0, '', '', '0', $phpAds_context);
      $phpAds_context[] = array('!=' => 'bannerid:'.$phpAds_raw['bannerid']);
      $phpAds_context[] = array('!=' => 'campaignid:'.$phpAds_raw['campaignid']);      
      echo $phpAds_raw['html'];

      if (trim($phpAds_raw['html']) != "") {
         //only one banner ad allowed for now.
         break;
      }
    }
    if (trim($phpAds_raw['html']) == "") {

         $phpAds_raw = view_local('+img', 0, 0, 0, '', '', '0', $phpAds_context);
         $phpAds_context[] = array('!=' => 'bannerid:'.$phpAds_raw['bannerid']);
         $phpAds_context[] = array('!=' => 'campaignid:'.$phpAds_raw['campaignid']);
         echo $phpAds_raw['html'];
		 
    }
	
  } 
}

echo "</div>";
//---------- Extract openxads
$temp[]="'";
$temp[]='"';

$regexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>"; 
if(preg_match_all("/$regexp/siU",  $phpAds_raw['html'], $matches)) { 
  /*echo "<script>alert('".str_replace($temp,"",$matches[2][0])."');</script>";*/
  $click_url = str_replace($temp,"",$matches[2][0]);
  
  //extract Banner ID
  $temp_banner = explode("__", $matches[2][0]);
  $pos = strpos($temp_banner[1],'bannerid=');
  if($pos === false){
    //Invalid banner id
    $banner_id = 0;
  }else{
    $banner_id = str_replace('bannerid=', '', $temp_banner[1]);
  }
   
  /*
  //echo "<script>alert('".$temp_banner[1]."');</script>";
  //echo "<script>alert('".$banner_id."');</script>";  
  //echo "<script>alert('".str_replace($temp,"",$matches[3][0])."');</script>";  
  //$image_tag = str_replace("'",'"',$matches[3][0]);
  */
}
/*
//Image parsing
    //if(preg_match_all('/(src)=("[^"]*")/i',$image_tag, $img)){
     //echo "<script>alert('".str_replace($temp,"",$img[2][0])."');</script>";  
     //echo "<script>alert('".str_replace($temp,"",$img[2][1])."');</script>";     
   //}
*/

//---------- Extract openxads


###################################    
###   Display Text Ads          ### 
###################################
echo "<div class=\"textAds\">";

$maxads_page = 1;

if ($tot_pages) {


for ($count = 0; $count < $maxads_page; $count++) {

  echo "<div align=\"center\" class=\"textAdsContainer\">";
  
   //<!--/* OpenX Local Mode Tag v2.6.3 */-->

  // The MAX_PATH below should point to the base of your OpenX installation
  define('MAX_PATH', '/var/www/plutoz.com/openx');
  //define('MAX_PATH', 'C:\apache2.2\htdocs\plutoz.com\openx');

  if (@include_once(MAX_PATH . '/www/delivery/alocal.php')) {
    if (!isset($phpAds_context)) {
      $phpAds_context = array();
    }
    foreach ($allwordstemp as $awtemp) {
      $awtemp = '+txt,+'.$awtemp;
      $phpAds_raw = view_local($awtemp, 0, 0, 0, '', '', '0', $phpAds_context);
      $phpAds_context[] = array('!=' => 'bannerid:'.$phpAds_raw['bannerid']);
      $phpAds_context[] = array('!=' => 'campaignid:'.$phpAds_raw['campaignid']);
      echo $phpAds_raw['html'];
    }
   
    if ($phpAds_raw['html'] == "") {
        $phpAds_raw = view_local('+txt', 0, 0, 0, '', '', '0', $phpAds_context);
        $phpAds_context[] = array('!=' => 'bannerid:'.$phpAds_raw['bannerid']);
        $phpAds_context[] = array('!=' => 'campaignid:'.$phpAds_raw['campaignid']);
        echo $phpAds_raw['html'];
    }
    if (stripos(strtolower($awtemp), 'travel')) {
      //echo "<div align='left'><a href='openx/popupads/7.jpg'><img src='resources/images/magnify.jpg' border='0'/></a></div>";	
          
    }
    if($banner_id > 0){
        echo "<script language='javascript' type='text/javascript'  src='openx/draw_magnify.php?banner_id=$banner_id'></script>";
        
      //echo "<div align='left'><a href='openx/magnify_imgloader.php?banner_id=$banner_id&ext=.jpg'><img src='resources/images/magnify.jpg' border='0'/></a></div>";
      echo "
      <script>
        function MagnifyClickRecorder(){
          document.getElementById('mag_iframe').src='$click_url';
        }
      </script>      
       ";
    }    
  }
  
  echo "</div>";
  
}

}

?>
		
<!-- Feedback Form Begin -->

<br /><br /><br />
<?php if ($tot_results > 0) {

         switch ($count_page) {
		    case 1: $disp = 0; break;
			case 2: $disp = 0; break;
			case 3: $disp = 300; break;
			case 4: $disp = 500; break;
			case 5: $disp = 800; break;
			case 6: $disp = 1000; break;
			case 7: $disp = 1200; break;
		  }
		
?>
<div style="margin-top:<?php echo $disp; ?>px;">
<center>
<form name="f" method="POST" action="">
  <table cellpadding='2' border=0 >
    <tr><td align="center"><font style="font-size: 15px; font-weight:bold; color: rgb(242, 121, 0);">Feedback</font></td></tr>
    <tr><td style="font-size: 12px; border-top: 1px solid #D2D2D2;" align="center">Are you Satisfied with your Search Results?</td>      
    </tr>
    <tr><td style="font-size: 12px; " align="center"><div><INPUT TYPE=RADIO NAME="feedback" VALUE="Yes">Yes&nbsp;&nbsp;
      <INPUT TYPE=RADIO NAME="feedback" VALUE="No">No</div></td></tr>
    <tr><td style="border-top: 1px solid #D2D2D2;" align="left">Comments:</td></tr>
    <tr><td><textarea rows="5" cols="30" name='comments' style="border:1px solid #666666; font-family:Arial, Helvetica, sans-serif; font-size:12px" wrap="soft"></textarea></td></tr>
    <tr><td align='center'><input type="image" src="resources/images/new_screens/reviews_submit_button.gif" /></td><tr>
  </table>
<input type='hidden' name='feedback_submitted' value='1'>
</form>
</center>
</div>
<?php } ?>

<?php
if($msg){
  echo "<div align='center'><font color='green' size=1><b>$msg</b></font></center>";
}
?>		
<!-- Feedback Form End-->	
<?php #################################################################################################### ?>
					</td>
				</tr>
				</table> <!-- Right Side Table End -->
			</td>
		</tr>
		<tr>
			<td><img src="resources/images/imagesbeta/left_bottom_img.png" width="534px" height="12px"></td>
			<td><img src="resources/images/imagesbeta/right_bottom_img.png" width="229px" height="12px"></td>
		</tr>
		</table>
	  </td>
	  <td width="45px">&nbsp;</td>
	</tr>
	</table> <!-- Main Table End -->
 </td>
</tr>
<tr>
<td height="30px">&nbsp;</td>
</tr>
</table>
<?php
###################################    
###   Bottom Page Links         ### 
###################################
require('footer.php');
?>     
<!-- for magnify image click recording -->    
<!-- <iframe id='mag_iframe' width=0 height=0 src=''></iframe>
 --></body>
</html>