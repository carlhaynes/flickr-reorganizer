<?php
/**
 * Created by IntelliJ IDEA.
 * User: carlhaynes
 * Date: 11/14/18
 * Time: 10:54 AM
 */
$configs = include('config.php');
echo json_encode($configs) . "\n";

$json_files = [];
$img_files = [];
$avi_files = [];

$from_dir = $configs['from_directory'];
if (!file_exists($from_dir)) {
    exit("$from_dir does not exist");
}
chdir($from_dir);

$to_dir = $configs['to_directory'] ;
if (!file_exists($to_dir)) {
    exit("$to_dir does not exist");
}

$scanned_directory = array_diff(scandir($from_dir), array('..', '.'));
foreach($scanned_directory as $file) {
    if ($file[0] == ".") {
        continue;
    }

    if (is_dir($from_dir . DIRECTORY_SEPARATOR . $file)) {
        if (in_array($file, $configs['ignore_directories'])) {
            echo "ignore directoy: " . $file . "\n";
            continue;
        }

        echo $from_dir . DIRECTORY_SEPARATOR . $file . "\n";
        $internal_files = array_diff(scandir($file), array('..', '.'));
        foreach ($internal_files as $internal_file) {
            //echo $internal_file . "\n";
            if ($internal_file[0] == ".") {
                echo "ignore: " . $internal_file . "\n";
                continue;
            }
            if (is_dir($internal_file)) {
                exit("nested directories !!!!!!");
            } else {
                $path_parts = pathinfo($internal_file);
                $extension = $path_parts['extension'];
                if ($extension == 'jpg') {
                    $img_file = $from_dir . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . $internal_file;;
                    $img_files[] = $img_file;
                    //echo " >> " . $img_file . "\n";
                } else if ($extension == 'json') {
                    $json_file = $from_dir . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . $internal_file;;
                    $json_files[] = $json_file;
                    //echo " >> " . $json_file . "\n";
                } else if ($extension == 'avi') {
                    $avi_file = $from_dir . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . $internal_file;;
                    $avi_files[] = $avi_file;
                    //echo " >> " . $avi_file . "\n";
                } else {
                    exit("unknown file type: " . $extension);
                }
            }
        }
    } else {
        $path_parts = pathinfo($file);
        if ($path_parts['extension'] != 'zip') {
            exit("unknown file type: " . $path_parts['extension']);
        }
    }

}

echo count($json_files) . " json files\n";
echo count($img_files) . " image files\n";
echo count($avi_files) . " video files\n";




