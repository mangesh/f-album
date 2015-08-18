<?php
    
    function current_page_url()
    {
        $page_url = 'http';
        if ($_SERVER["HTTPS"] == "on") {
            $page_url .= "s";
        }
        $page_url .= "://";
        $page_url .= $_SERVER["SERVER_NAME"];
        return $page_url;
    }   

    function recursive_remove_directory($directory)
    {
        foreach (glob("{$directory}/*") as $file) {
            if (is_dir($file)) {
                recursive_remove_directory($file);
            } else {
                unlink($file);
            }
        }
        rmdir($directory);
    }

    function recursive_check_directory_name($directory, $name)
    {
        foreach (glob("{$directory}/*") as $i => $file) {
            if (is_dir($file) && $file == $name) {
                $prefix = substr($file, -1);
                if ((int)$prefix !== 0) {
                    $file = substr($file, 0, -1);
                }
                $prefix = (int)$prefix+1;

                $name = recursive_check_directory_name($directory, trim($file).' '.$prefix);
            }
        }
        return $name;
    }

    function filename_safe($name)
    {
        $except = array('\\', '/', ':', '*', '?', '"', '<', '>', '|', '.', '(', ')', ';');
        return str_replace($except, '', $name);
    }

    function create_album($album_name = '')
    {
        if ($album_name == '') {
            return false;
        }
        //This is the authentication header we'll need to pass with each successive call
        $authHeader = 'Authorization:  Bearer '.$_SESSION['g_access_token'].'"';
        $userId = "default";
        $feedUrl = "https://picasaweb.google.com/data/feed/api/user/$userId";

        //This is the XML for creating a new album.
        $rawXml = "<entry xmlns='http://www.w3.org/2005/Atom'
                        xmlns:media='http://search.yahoo.com/mrss/'
                        xmlns:gphoto='http://schemas.google.com/photos/2007'>
                        <title type='text'>".$album_name."</title>
                        <summary type='text'>Facebook album uploaded via f-album app</summary>
                        <gphoto:access>private</gphoto:access>
                        <gphoto:timestamp>".time()."</gphoto:timestamp>
                        <category scheme='http://schemas.google.com/g/2005#kind'
                        term='http://schemas.google.com/photos/2007#album'></category>
                    </entry>";

        $ch = curl_init();
        $options = array(
                    CURLOPT_URL => $feedUrl,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_VERBOSE => 0,
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HEADER => false,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_POSTFIELDS => $rawXml,
                    CURLOPT_HTTPHEADER => array('GData-Version:  2', $authHeader, 'Content-Type:  application/atom+xml')
                );
        curl_setopt_array($ch, $options);

        $response   = curl_exec($ch);
        $album      = simplexml_load_string($response);
        return $album;
    }

    function upload_photos_to_picasa($album_id = '', $photos_dir = '')
    {
        if ($album_id == '') {
            return false;
        }
        
        //This is the authentication header we'll need to pass with each successive call
        $authHeader = 'Authorization:  Bearer '.$_SESSION['g_access_token'].'"';
        foreach (glob("{$photos_dir}/*") as $i => $file) {
            if (!is_dir($file)) {
                $albumUrl = $album_id;
                $imgName = $_SERVER['DOCUMENT_ROOT'].'/'.$file;

                /**** Use this code to upload image with metadata ****/
                /*$rawImgXml = '<entry xmlns="http://www.w3.org/2005/Atom">
                              <title>image.jpg</title>
                              <summary>Picture from f-album.</summary>
                              <category scheme="http://schemas.google.com/g/2005#kind"
                                term="http://schemas.google.com/photos/2007#photo"/>
                            </entry>';

                $fileSize   = filesize($imgName);
                $fh         = fopen($imgName, 'rb');
                $imgData    = fread($fh, $fileSize);
                fclose($fh);

                $dataLength = strlen($rawImgXml) + $fileSize;
                $data = "";
                $data .= "\nMedia multipart posting\n";
                $data .= "--P4CpLdIHZpYqNn7\n";
                $data .= "Content-Type: application/atom+xml\n\n";
                $data .= $rawImgXml . "\n";
                $data .= "--P4CpLdIHZpYqNn7\n";
                $data .= "Content-Type: image/jpeg\n\n";
                $data .= $imgData . "\n";
                $data .= "--P4CpLdIHZpYqNn7--";

                $header = array(
                            'GData-Version:  2',
                            $authHeader,
                            'Content-Type: multipart/related; boundary=P4CpLdIHZpYqNn7;',
                            'Content-Length: ' . strlen($data),
                            'MIME-version: 1.0'
                        );

                $ret = "";
                $ch  = curl_init($albumUrl);
                $options = array(
                        CURLOPT_SSL_VERIFYPEER=> false,
                        CURLOPT_POST=> true,
                        CURLOPT_RETURNTRANSFER=> true,
                        CURLOPT_HEADER=> true,
                        CURLOPT_FOLLOWLOCATION=> true,
                        CURLOPT_POSTFIELDS=> $data,
                        CURLOPT_HTTPHEADER=> $header
                    );*/

                //Get the binary image data
                $fileSize = filesize($imgName);
                $fh = fopen($imgName, 'rb');
                $imgData = fread($fh, $fileSize);
                fclose($fh);

                $header = array(
                            'GData-Version:  2',
                            $authHeader,
                            'Content-Type: image/jpeg',
                            'Content-Length: ' . $fileSize,
                            'Slug: image_'.($i+1).'.jpg'
                        );
                $data = $imgData;//Needs to be base64 encoded

                $ret = "";
                $ch  = curl_init($albumUrl);
                $options = array(
                        CURLOPT_SSL_VERIFYPEER=> false,
                        CURLOPT_POST=> true,
                        CURLOPT_RETURNTRANSFER=> true,
                        CURLOPT_HEADER=> true,
                        CURLOPT_FOLLOWLOCATION=> true,
                        CURLOPT_POSTFIELDS=> $data,
                        CURLOPT_HTTPHEADER=> $header
                    );
                curl_setopt_array($ch, $options);
                $ret = curl_exec($ch);
                curl_close($ch);
            }
        }
        
    }

    function update_token($google_cred, $refresh_token)
    {
        $clientSecret   = $google_cred['client_secret'];
        $referer        = 'http://fb.dev/home';

        $postBody = 'refresh_token='.urlencode($refresh_token)
                  .'&grant_type=refresh_token'
                  .'&client_id='.urlencode($google_cred['client_id'])
                  .'&client_secret='.urlencode($google_cred['client_secret']);

        $curl = curl_init();
        curl_setopt_array(
            $curl,
            array( CURLOPT_CUSTOMREQUEST => 'POST'
               , CURLOPT_URL => 'https://accounts.google.com/o/oauth2/token'
               , CURLOPT_HTTPHEADER => array( 'Content-Type: application/x-www-form-urlencoded'
                                             , 'Content-Length: '.strlen($postBody)
                                             , 'User-Agent: YourApp/0.1 +http://fb.dev/home'
                                             )
               , CURLOPT_POSTFIELDS => $postBody
               , CURLOPT_REFERER => $referer
               , CURLOPT_RETURNTRANSFER => 1
               , CURLOPT_TIMEOUT => 120
               , CURLOPT_FOLLOWLOCATION => 0
               , CURLOPT_FAILONERROR => 0
               , CURLOPT_SSL_VERIFYPEER => 0
               , CURLOPT_VERBOSE => 0
            )
        );
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $response = (array)json_decode($response);

        if ($http_code === 200) {
            return $response;
        } else {
            return false;
        }
    }