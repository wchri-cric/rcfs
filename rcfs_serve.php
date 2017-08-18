<?php
/*
 * PLUGIN NAME:	REDCap File Server
 * DESCRIPTION: "Serves" static project and DAG specific files.
 * VERSION:		0.1
 * DATE:		2017-08-18
 * AUTHOR:		Rick Watts (rick.watts@ualberta.ca)
 */

// define the root of the document tree for the included files
$file_root='/home/reporting/rcfs/';

// Call the REDCap Connect file in the main "redcap" directory
require_once "../redcap_connect.php";

// Display the project header
//require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// Check that the user has reporting rights in the current project

$rights = REDCap::getUserRights($USERID);
if (empty($rights)) exit("User USERID does NOT have access to this project.");
$user=$rights[USERID][username];
if ($rights[USERID]['data_export_tool'] < 1) exit("<b>Error: $user you need export and reporting rights to use this plugin.</b>");

// Check if the user is in a data access group (DAG)
$group_id = $rights[$user]['group_id'];
// If $group_id is blank, then user is not in a DAG
if ($group_id == '') $dag=false;
else
{
    // User is assigned to a DAG, so get the DAG's name to display
    $dag=true;
    $dagid=REDCap::getGroupNames(true, $group_id);
}

// If the user is in a DAG then does this file belong to their DAG?
if ($dag and $dagid != $_GET['dag']) exit("<b>Error: you do not have access to this DAG</b>");

// Set up the file path

if ($_GET['dag'] == "") $file=$file_root.$_GET['pid']."/".$_GET['file'];
else $file=$file_root.$_GET['pid']."/".$_GET['dag']."/".$_GET['file'];

// Work out what type of file it is

$ext=substr(strrchr($file,'.'),1);


// And handle the file

if (!file_exists($file)) exit("Error: ". $file. " file not found!");
{
    switch($ext)
    {
        case "html":
        case "htm":
        case "log":
        case "txt":
            readfile($file);
            break;
        case "pdf":
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . basename($file));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            ob_clean();
            flush();
            readfile($file);
            break;
        case "png":
            header('Content-Type: image/png');
            readfile($file);
            break;
        case "jpg":
        case "jpeg":
            header('Content-Type: image/jpeg');
            readfile($file);
            break;
        case "gif":
            header('Content-Type: image/gif');
            readfile($file);
            break;
        default:
            exit("Unhandled file type: " . $file);
    }
}


