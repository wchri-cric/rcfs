<?php
/*
 * PLUGIN NAME:	REDCap File Server
 * DESCRIPTION: "Serves" static project and DAG specific files.
 * VERSION:		0.1
 * DATE:		2017-08-18
 * AUTHOR:		Rick Watts (rick.watts@ualberta.ca)
 */

$file_root='/home/reporting/rcfs/';
$handled_extensions="htm html txt log png gif jpeg jpg pdf";

// Call the REDCap Connect file in the main "redcap" directory
require_once "../redcap_connect.php";

function get_html_title($page)
{
    // Opens an html file and retrieves the title metadata

    $html = file_get_contents($page);
    preg_match("/<title>([^<]*)<\/title>/im", $html, $matches);
    return $matches[1];
}

function cmp($a, $b)
{
    // sort function to be used by usort()
    return strcmp($a["dir"].$a["title"].$a["file"], $b["dir"].$b["title"].$b["file"]);
}

function directoryScan($dir, $onlyfiles = false, $fullpath = false)
{
    // Retrieves a list of files/folders from the O/S

    if (isset($dir) && is_readable($dir)) {
        $dlist = Array();
        $dir = realpath($dir);
        if ($onlyfiles) {
            $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        } else {
            $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::SELF_FIRST);
        }
   
        foreach($objects as $entry => $object){ 
            if (!$fullpath) {
                $entry = str_replace($dir, '', $entry);
            }
             
            $dlist[] = $entry;
        }
        return $dlist;
    }
}

// Initialize a few variables

$dagname='';
$dagid='';

// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// and a title
print("<h3 style=\"color:#800000;\">REDCap External Document and Report Plugin </h3>");

// Check that the user has reporting rights in the current project

$rights = REDCap::getUserRights(USERID);

if (empty($rights)) exit("User USERID does NOT have access to this project.");
$user=$rights[USERID][username];
if ($rights[USERID]['data_export_tool'] < 1) exit("<b>Error: $user you need export and reporting rights to use this plugin.</b>");	

// Check if the user is in a data access group (DAG)

$group_id = $rights[$user]['group_id'];
// If $group_id is blank, then user is not in a DAG
if ($group_id == '')
{
    print "User $user is NOT assigned to a data access group.<br>";
    $dag=false;
} else
{
    // User is assigned to a DAG, so get the DAG's name to display
    $dag=true;
    $dagname=REDCap::getGroupNames(false, $group_id);
    $dagid=REDCap::getGroupNames(true, $group_id);
    print "User $user is assigned to the DAG named \"" . $dagname . "\", whose unique group name is \"" . $dagid . "\".<br>";
}

print("<b><br>Available documents based on Project and Data Access Group (if any).</b><p>");

// Get a list of groups in the project - we'll need it later

$group_list=array_combine(REDCap::getGroupNames(true),REDCap::getGroupNames(false));

// Scan through the folders from the file root

if ($dag)
    $file_root=$file_root.$project_id.'/'.$dagid;
else
    $file_root=$file_root.$project_id;

if (!file_exists($file_root))
{
    print "<br><b>Project/DAG folder ".$file_root." not found.</b>";
    require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
    exit();
}

$fileTree = directoryScan($file_root, false, true);

// Create a new array containing "records" for each file

$filelist=array();
$i=0;
foreach($fileTree as $item)
{
    $basename=basename($item);
    $ext=substr(strrchr($item,'.'),1);
    if ($basename != '.' and $basename != '..')
    {
        if (!is_dir($item))
        {    // This item should be a file so check to see if it is a handled type and add to list
            if (strpos($handled_extensions,$ext))
            {
	        $file=$basename;
	        $dir=basename(dirname($item));
                if ($dir == $_GET[pid]) $dir="";

                switch($ext)
                {
                    case "html":
                    case "htm":
                        $title=get_html_title($item);
                        if ($title=="" or ctype_space($title)) $title="Untitled HTML document";
                        break;
                    case "log":
                        $title="Log file";
                        break;
                    case "txt":
                        $title="Text file";
                        break;
                    case "png":
                        $title="Graphics output";
                        break;
                    default:
                        $title="Untitled file";
                }
                    
                $filelist[$i]=array('dir'=>$dir,'file'=>$file,'item'=>$item,'title'=>$title,'ext'=>$ext);
                // print_r($filelist[$i]); print("<br>");
                $i++;
            }
        }
    }
}
// Sort the list by file title and then again by folder (seems like there should be a better way to do this)
usort($filelist,"cmp");
//var_dump($filelist);

$current_dag="";
print('<table style="width:100%">');

//Set some widths if we need to
//print("<tr><td width=\"50%\"></td><td width=\"25%\"></td><td width=\"25%\"></td></tr>");

foreach($filelist as $item)
{
//   print("<br><br>");
//   print_r($item);
//   print("<br><br>");

    if ($item['dir'] != $current_dag)
    {
        // this is a new DAG
        $current_dag=$item['dir'];
        print("<tr><td><br><b>$group_list[$current_dag]</b> ($current_dag)<br></td></tr>");
    }
    
    $startcell="<td><a href=\"" . APP_PATH_WEBROOT_FULL . "plugins/rcfs_serve.php?pid=" . $project_id . "&dag=" . $current_dag . "&file=" . $item['file']."\" /* target=\"_blank\"*/ >";
    $endcell="</a></td>";
    print("<tr>");
    print($startcell.$item['title'].$endcell);
    print($startcell.$item['file'].$endcell);
    print($startcell.date("Y-M-d H:i",filemtime($item['item'])).$endcell);
    print("</tr>");
}

print("</table>");

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';