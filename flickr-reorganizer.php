<?php
/**
 * Created by IntelliJ IDEA.
 * User: carlhaynes
 * Date: 11/14/18
 * Time: 10:54 AM
 */
$configs = include('config.php');
echo json_encode($configs) . "\n";

$album_list = null;
$no_album_path = null;

$photo_json_files = [];
$img_files = [];

$albums = [];
$missing_files = [];


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

                $fileId = null;

                $path_parts = pathinfo($internal_file);
                $extension = $path_parts['extension'];
                if ($extension == 'jpg') {
                    $img_file = $from_dir . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . $internal_file;;
                    $fileId = getFileId($internal_file, "_o.$extension");
                    if (isset($img_files[$fileId])) {
                        exit("file already set: $img_file");
                    }
                    $img_files[$fileId] = $img_file;
                   // echo " >> " . $img_file . " / $fileId" . "\n";
                } else if ($extension == 'png') {
                    $img_file = $from_dir . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . $internal_file;
                    $fileId = getFileId($internal_file, "_o.$extension");
                    if (isset($img_files[$fileId])) {
                        exit("file already set: $img_file");
                    }

                    $img_files[$fileId] = $img_file;
                    //echo " >> " . $img_file . "\n";
                } else if ($extension == 'json') {
                    if ($internal_file == 'albums.json') {
                        $json_file = $from_dir . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . $internal_file;;
                        $album_list = $json_file;
                        continue;
                    }

                    if (strpos($internal_file, 'photo_') !== 0) {
                        continue;
                    }



                    $json_file = $from_dir . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . $internal_file;
                    $photo_json_files[] = $json_file;

                    //echo " >> " . $json_file . "\n";
                } else if ($extension == 'avi') {
                    $avi_file = $from_dir . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . $internal_file;;
                    $fileId = getFileId($internal_file, ".$extension");
                    if (isset($img_files[$fileId])) {
                        echo("file already set: $avi_file ($fileId)\n");
                        //exit("file already set: $avi_file");
                    } else {
                        echo("setting: $avi_file ($fileId)\n");
                    }
                    $img_files[$fileId] = $avi_file;
                    //echo " >> " . $avi_file . "\n";
                } else {
                    exit("unknown file type: " . $extension . " - $internal_file");
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

echo count($photo_json_files) . " photo_json_files\n";
echo count($img_files) . " image files\n";


if (!$album_list) {
    exit("Did not find album listing");
}

$json_string = file_get_contents($album_list);
$data = json_decode($json_string, true);

foreach($data['albums'] as $album) {

    try {
        $album['path'] = $to_dir . DIRECTORY_SEPARATOR . filename_safe($album['title']);
        if (!file_exists($album['path'])) {
            echo "making dir: {$album['path']}\n";
            mkdir($album['path']);
        } else {
            echo "album dir exists: {$album['path']}\n";
        }
    } catch(Exception $e) {
        exit($e);
    }

    $albums[$album['id']] = $album;

    foreach($album['photos'] as $photoId) {
        if (!isset($img_files[$photoId])) {
            $missing_files[] = $missing_files;
        }
    }
}

// create the no album path
$no_album_path = $to_dir . DIRECTORY_SEPARATOR . "no_album";
if (!file_exists($no_album_path)) {
    mkdir($no_album_path);
}




foreach($photo_json_files as $photo_json_file) {
    $json_string = file_get_contents($photo_json_file);
    $data = json_decode($json_string, true);

    if (!isset($data['name'])) {
        echo($photo_json_file . "\n");
        echo "no name: " . json_encode($data) . "\n";
        exit();
    }

    $photoId = $data['id'];
    if (!isset($img_files[$photoId])) {
        echo "no file: " . $photoId . "\n";
        continue;
    }
    $originalPhoto = $img_files[$photoId];

    $albumDatas = $data['albums'];

    if (count($albumDatas) == 0) {
        if ($configs['copy_files']) {
            copyToPath($originalPhoto, $no_album_path);
        } else {
            moveToPath($originalPhoto, $no_album_path);
        }
    } else {
        foreach($albumDatas as $albumData) {

            $albumId = $albumData['id'];
            $album = $albums[$albumId];

            if ($configs['copy_files']) {
                copyToPath($originalPhoto, $album['path']);
            } else {
                //TODO: more than one album, need to move to one, then copy to the others
                moveToPath($originalPhoto, $album['path']);
            }
        }
    }

}


echo("There were " . count($albums) . " albums\n");

echo("There were " . count($missing_files) . " missing photos from albums\n");

exit("done");





function getFileId($file, $extension) {
    $fileId = $file;
    $pos = strrpos( $fileId, $extension);
    if ($pos !== false) {
        $fileId = substr($fileId, 0, $pos);
        $pos = strrpos( $fileId, '_');
        if ($pos !== false) {
            $fileId = substr($fileId, $pos+1);
        } else {
            $fileId = null;
        }
    } else {
        $fileId = null;
    }
   // echo "$fileId\n";
    return $fileId;
}

function copyToPath($file, $newPath) {
    if (!$file || !$newPath) {
        echo "no file or detination path set\n";
        exit();
    }

    $fileName = substr($file, strrpos($file, '/') +1);
    $newFile = $newPath . DIRECTORY_SEPARATOR . $fileName;


    if (!file_exists($newFile)) {
        echo "copying $file to $newFile \n";
        if (!copy($file, $newFile)) {
            exit("error while copying file $file to $newFile");
        }
    }
}

function moveToPath($file, $newPath) {
    if (!$file || !$newPath) {
        echo "no file or detination path set\n";
        exit();
    }

    $fileName = substr($file, strrpos($file, '/') +1);
    $newFile = $newPath . DIRECTORY_SEPARATOR . $fileName;


    if (!file_exists($newFile)) {
        echo "moving $file to $newFile \n";

        //
        // TODO: uncomment here to move, I leave it commented  out so I don't accidently move files
        // this has NOT been tested
        //
        //rename($file, $newFile);
    }
}


// stole this from the php docs
function filename_safe($name) {
    $except = array('\\',
        '/',
        ':',
        '*',
        '?',
        '"',
        '<',
        '>',
        '|',
        ' ',
        '\'',
        ',',
        '.');
    return str_replace($except, '_', trim($name));
}
