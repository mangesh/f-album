<?php

/******************************* LOADING & INITIALIZING BASE APPLICATION ****************************************/

// Configuration for error reporting, useful to show every little problem during development
error_reporting(E_ALL);
ini_set("display_errors", 1);
set_time_limit(0);
session_start();

if (!extension_loaded('openssl')) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        dl('php_openssl.dll');
    } else {
        dl('openssl.so');
    }
}

// Load Composer's PSR-4 autoloader (necessary to load Slim, Mini etc.)
require '../lib/vendor/autoload.php';

use Alchemy\Zippy\Zippy;

// Initialize Slim (the router/micro framework used)
$app = new \Slim\Slim(array(
    'mode' => 'development'
));

// and define the engine used for the view @see http://twig.sensiolabs.org
$app->view = new \Slim\Views\Twig();
$app->view->setTemplatesDirectory("../Mini/view");

/******************************************* THE CONFIGS *******************************************************/

// Configs for mode "development" (Slim's default), see the GitHub readme for details on setting the environment
$app->configureMode('development', function () use ($app) {

    // pre-application hook, performs stuff before real action happens @see http://docs.slimframework.com/#Hooks
    $app->hook('slim.before', function () use ($app) {

        // SASS-to-CSS compiler @see https://github.com/panique/php-sass
        //SassCompiler::run("scss/", "css/");

        // CSS minifier @see https://github.com/matthiasmullie/minify
        //$minifier       = new MatthiasMullie\Minify\CSS('css/style.css');
        //$sourcePath2    = '../lib/vendor/twbs/bootstrap/dist/css/bootstrap.css';
        //$sourcePath3 = 'css/cover.css';
        //$minifier->add($sourcePath2);
        //$minifier->minify('css/style.css');

        // JS minifier @see https://github.com/matthiasmullie/minify
        // DON'T overwrite your real .js files, always save into a different file
        //$minifier = new MatthiasMullie\Minify\JS('../lib/vendor/twbs/bootstrap/dist/js/bootstrap.js');
        //$minifier->minify('js/bootstrap.min.js');
    });

    require 'inc/config.php';
    // Set the configs for development environment
    $app->config(array(
        'debug' => true,
        'database' => array(
            'db_host' => $db['host'],
            'db_port' => '',
            'db_name' => $db['db'],
            'db_user' => $db['user'],
            'db_pass' => $db['password']
        ),
        'fb' => array(
            'app_id'        => $fb['app_id'],
            'app_secret'    => $fb['app_secret'],
            'callback_url'  => $fb['callback_url']
        ),

        'google' => array(
            'client_id'         => $google['client_id'],
            'client_secret'     => $google['client_secret'],
            'callback_url'      => $google['callback_url']
        )

    ));
});

// Configs for mode "production"
$app->configureMode('production', function () use ($app) {
    // Set the configs for production environment
    $app->config(array(
        'debug' => false,
        'log.enable' => true,
        'database' => array(
            'db_host' => '',
            'db_port' => '',
            'db_name' => '',
            'db_user' => '',
            'db_pass' => ''
        )
    ));
});

/******************************************** THE MODEL ********************************************************/

// Initialize the model, pass the database configs. $model can now perform all methods from Mini\model\model.php
$model = new \Mini\Model\Model($app->config('database'));

$fb_cred = $app->config('fb');

$fb = new Facebook\Facebook([
        'app_id'                => $fb_cred['app_id'],
        'app_secret'            => $fb_cred['app_secret'],
        'default_graph_version' => 'v2.2',
    ]);

/************************************ Google Login API ********************************************************/

/*$client = new Google_Client();
$client->setAuthConfigFile('inc/client_secrets.json');
$client->addScope(Google_Service_Drive::DRIVE_METADATA_READONLY);

if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
  $client->setAccessToken($_SESSION['access_token']);
  $drive_service = new Google_Service_Drive($client);
  $files_list = $drive_service->files->listFiles(array())->getItems();
  echo json_encode($files_list);
} else {
  $redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . '/oauth2callback.php';
  header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
}*/

/************************************ THE ROUTES / CONTROLLERS *************************************************/

// GET request on homepage, simply show the view template index.twig
$app->get('/', function () use ($app, $fb) {
    $fb_cred = $app->config('fb');

    /* If access tokens are not available redirect to connect page. */
    if (!empty($_SESSION['fb_access_token'])) {
        $app->redirect('/home');
        exit;
    } else {
        $helper         = $fb->getRedirectLoginHelper();
        $permissions    = ['email', 'user_likes', 'user_photos'];
        $loginUrl       = $helper->getLoginUrl($fb_cred['callback_url'], $permissions);

        $app->render('index.twig', array(
            'login_url' => $loginUrl
        ));
    }

});

function update_token($google_cred, $refresh_token){
    $clientId       = $google_cred['client_id'];
    $clientSecret   = $google_cred['client_secret']; 
    $referer        = 'http://fb.dev/home';

    $postBody = 'refresh_token='.urlencode($refresh_token)
              .'&grant_type=refresh_token'
              .'&client_id='.urlencode($google_cred['client_id'])
              .'&client_secret='.urlencode($google_cred['client_secret']);

    $curl = curl_init();
            curl_setopt_array( $curl,
                array( CURLOPT_CUSTOMREQUEST => 'POST'
                       , CURLOPT_URL => 'https://accounts.google.com/o/oauth2/token'
                       , CURLOPT_HTTPHEADER => array( 'Content-Type: application/x-www-form-urlencoded'
                                                     , 'Content-Length: '.strlen($postBody)
                                                     , 'User-Agent: YourApp/0.1 +http://fb.dev/home'
                                                     )
                       , CURLOPT_POSTFIELDS => $postBody                              
                       , CURLOPT_REFERER => $referer
                       , CURLOPT_RETURNTRANSFER => 1 // means output will be a return value from curl_exec() instead of simply echoed
                       , CURLOPT_TIMEOUT => 120 // max seconds to wait
                       , CURLOPT_FOLLOWLOCATION => 0 // don't follow any Location headers, use only the CURLOPT_URL, this is for security
                       , CURLOPT_FAILONERROR => 0 // do not fail verbosely fi the http_code is an error, this is for security
                       , CURLOPT_SSL_VERIFYPEER => 0 // do verify the SSL of CURLOPT_URL, this is for security
                       , CURLOPT_VERBOSE => 0 // don't output verbosely to stderr, this is for security
                )
            );
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $response = (array)json_decode($response);

    /*curl_close($curl);  
    var_dump($response = json_decode($response));
    var_dump($http_code); die();*/
    if ($http_code === 200) {
        return $response;
    } else {
        return false;
    }
}

$app->get('/google_callback', function () use ($app, $model, $fb) {
    $google_cred = $app->config('google');
    // visit this page in a browser
    // https://accounts.google.com/o/oauth2/auth?scope=https://picasaweb.google.com/data/&response_type=code&access_type=offline&redirect_uri=https://fb.dev/yourapp/oauth2.php&approval_prompt=force&client_id=407408718192.apps.googleusercontent.com
    //
    // and click approve and you will be redirected here
    // https://fb.dev/yourapp/oauth2.php?code=PkqD6reYULMKKrrasEB2G5D0osYXmFaGhtV8o4/WqYg8dkXvfARQvtJS9iHerQj1C0.4iWMDCKlAI
    //
    // which will send the code to
    // https://accounts.google.com/o/oauth2/token
    //
    // and get a response like the follwing
    // { "access_token"  : "ym2BEd9.ej9XnOINtBVUbDa222Oy0bYXhhT_mvGaD4ixiSXYwD4Y8lv0EyzggaV6Ifn7MMyoYTJdoaqL0zA"
    // , "token_type"    : "Bearer"
    // , "expires_in"    : 3600
    // , "refresh_token" : "1/Pv3c5PgT6s3bFxndmfv89sIWls-LgTvMEudVrK5jSx2P9mTDCzXwupoR30zcRFq6" 
    // }
    if (empty($_SESSION['fb_access_token'])) {
        $app->redirect('/clearsession');
        exit;
    }
    /*if ($_SESSION['g_access_token']) {
        $app->redirect('/home');
        exit;
    }*/
    if (isset($_GET['code']))
    {
        $clientId       = $google_cred['client_id'];
        $clientSecret   = $google_cred['client_secret']; 
        $referer        = 'http://fb.dev/home';

        $postBody = 'code='.urlencode($_GET['code'])
                  .'&grant_type=authorization_code'
                  .'&redirect_uri='.urlencode($google_cred['callback_url'])
                  .'&client_id='.urlencode($google_cred['client_id'])
                  .'&client_secret='.urlencode($google_cred['client_secret']);

        $curl = curl_init();
                curl_setopt_array( $curl,
                    array( CURLOPT_CUSTOMREQUEST => 'POST'
                           , CURLOPT_URL => 'https://accounts.google.com/o/oauth2/token'
                           , CURLOPT_HTTPHEADER => array( 'Content-Type: application/x-www-form-urlencoded'
                                                         , 'Content-Length: '.strlen($postBody)
                                                         , 'User-Agent: YourApp/0.1 +http://fb.dev/home'
                                                         )
                           , CURLOPT_POSTFIELDS => $postBody                              
                           , CURLOPT_REFERER => $referer
                           , CURLOPT_RETURNTRANSFER => 1 // means output will be a return value from curl_exec() instead of simply echoed
                           , CURLOPT_TIMEOUT => 120 // max seconds to wait
                           , CURLOPT_FOLLOWLOCATION => 0 // don't follow any Location headers, use only the CURLOPT_URL, this is for security
                           , CURLOPT_FAILONERROR => 0 // do not fail verbosely fi the http_code is an error, this is for security
                           , CURLOPT_SSL_VERIFYPEER => 0 // do verify the SSL of CURLOPT_URL, this is for security
                           , CURLOPT_VERBOSE => 0 // don't output verbosely to stderr, this is for security
                    )
                );
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        /*curl_close($curl);  
        var_dump($response = json_decode($response));
        var_dump($http_code); die();*/
        if ($http_code === 200 and (isset($_SESSION['user_id']))) {
            $response = (array)json_decode($response);
            $_SESSION['g_access_token']     = $response["access_token"];
            $_SESSION['refresh_token']      = $response["refresh_token"];
            $_SESSION['expires_in']         = time()+$response["expires_in"];

            $model->updateToken(
                $_SESSION['user_id'],
                $response["access_token"],
                $response["refresh_token"],
                $response["token_type"],
                $_SESSION['expires_in']
            );
        }
       
        ?>
        <!DOCTYPE html>
        <html>
            <head>
                <meta name="viewport" content="width=device-width, initial-scale=1" />
                <meta http-equiv="content-type" content="text/html; charset=utf-8" />
                <title>Sign In</title>
                <script type="text/javascript">
                    function closeThis()
                    {
                        window.opener.oauthComplete(); 
                        window.close();
                    }
                </script>
            </head>
            <body onload="closeThis();">
            </body>
        </html>
    <?php
    }
    else { echo 'Code was not provided.'; }

});

$app->get('/callback', function () use ($app, $model, $fb) {
    $fb_cred = $app->config('fb');
    $google_cred = $app->config('google');
    
    $helper = $fb->getRedirectLoginHelper();

    try {
        $accessToken = $helper->getAccessToken();
    } catch (Facebook\Exceptions\FacebookResponseException $e) {
        // When Graph returns an error
        echo 'Graph returned an error: ' . $e->getMessage();
        exit;
    } catch (Facebook\Exceptions\FacebookSDKException $e) {
        // When validation fails or other local issues
        echo 'Facebook SDK returned an error: ' . $e->getMessage();
        exit;
    }

    if (! isset($accessToken)) {
        if ($helper->getError()) {
            header('HTTP/1.0 401 Unauthorized');
            echo "Error: " . $helper->getError() . "\n";
            echo "Error Code: " . $helper->getErrorCode() . "\n";
            echo "Error Reason: " . $helper->getErrorReason() . "\n";
            echo "Error Description: " . $helper->getErrorDescription() . "\n";
        } else {
            header('HTTP/1.0 400 Bad Request');
            echo 'Bad request';
        }
        exit;
    }

    // The OAuth 2.0 client handler helps us manage access tokens
    $oAuth2Client = $fb->getOAuth2Client();

    // Get the access token metadata from /debug_token
    $tokenMetadata = $oAuth2Client->debugToken($accessToken);
    //echo '<h3>Metadata</h3>';
    //var_dump($tokenMetadata);

    // Validation (these will throw FacebookSDKException's when they fail)
    $tokenMetadata->validateAppId($fb_cred['app_id']);
    // If you know the user ID this access token belongs to, you can validate it here
    //$tokenMetadata->validateUserId('123');
    $tokenMetadata->validateExpiration();

    if (! $accessToken->isLongLived()) {
        // Exchanges a short-lived access token for a long-lived one
        try {
            $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            echo "<p>Error getting long-lived access token: " . $helper->getMessage() . "</p>\n\n";
            exit;
        }

        //echo '<h3>Long-lived</h3>';
        //var_dump($accessToken->getValue());
    }

    $_SESSION['fb_access_token'] = (string) $accessToken;

    try {
        // Returns a `Facebook\FacebookResponse` object
        $response = $fb->get('/me?fields=id,name,email', $_SESSION['fb_access_token']);
    } catch (Facebook\Exceptions\FacebookResponseException $e) {
        echo 'Graph returned an error: ' . $e->getMessage();
        exit;
    } catch (Facebook\Exceptions\FacebookSDKException $e) {
        echo 'Facebook SDK returned an error: ' . $e->getMessage();
        exit;
    }

    $user = $response->getGraphUser();
    
    if (isset($user)) {
        $_SESSION['user_id']    = $user["id"];
        $_SESSION['user_name']  = $user["name"];
        $is_user_exists = (array)$model->getUser($user["id"]);

        if (!$is_user_exists) {
            $model->addUser(
                $user["id"],
                $user["name"],
                $user["email"]
            );
        } else {
            if($is_user_exists['g_access_token'] <> ''){
                if ($is_user_exists['expires_in'] < time()) {
                    $refresh = update_token($google_cred, $is_user_exists['refresh_token']);
                    if($refresh){
                        $response = $refresh;
                        $_SESSION['g_access_token']     = $response["access_token"];
                        $_SESSION['refresh_token']      = $is_user_exists['refresh_token'];
                        $_SESSION['expires_in']         = time()+$response["expires_in"];

                        $model->updateToken(
                            $_SESSION['user_id'],
                            $response["access_token"],
                            $is_user_exists['refresh_token'],
                            $response["token_type"],
                            $_SESSION['expires_in']
                        );
                    }
                }
            }
        }
        $app->redirect('/home');
    }
    
});

$app->get('/home', function () use ($app, $model, $fb) {
    
    $fb_cred = $app->config('fb');
    $google_cred = $app->config('google');

    /* If access tokens are not available redirect to connect page. */
    if (empty($_SESSION['fb_access_token'])) {
        $app->redirect('/clearsession');
        exit;
    }
    /* Get user access tokens out of the session. */
    $accessToken = $_SESSION['fb_access_token'];
    
    $fb->setDefaultAccessToken($_SESSION['fb_access_token']);
    
    // Send the request to Graph
    try {
        //$response = $fb->getClient()->sendRequest($request);
    } catch (Facebook\Exceptions\FacebookResponseException $e) {
        // When Graph returns an error
        echo 'Graph returned an error: ' . $e->getMessage();
        exit;
    } catch (Facebook\Exceptions\FacebookSDKException $e) {
        // When validation fails or other local issues
        echo 'Facebook SDK returned an error: ' . $e->getMessage();
        exit;
    }

    /* User profile picture */
    $img= "https://graph.facebook.com/".$_SESSION['user_id']."/picture";

    $is_user_exists = (array)$model->getUser($_SESSION['user_id']);
    
    if ($is_user_exists) {
        if($is_user_exists['g_access_token'] <> ''){
            if ($is_user_exists['expires_in'] < time()) {
                $refresh = update_token($google_cred, $is_user_exists['refresh_token']);
                if($refresh){
                    $response = $refresh;
                    $_SESSION['g_access_token']     = $response["access_token"];
                    $_SESSION['refresh_token']      = $is_user_exists['refresh_token'];
                    $_SESSION['expires_in']         = time()+$response["expires_in"];

                    $model->updateToken(
                        $_SESSION['user_id'],
                        $response["access_token"],
                        $refresh_token,
                        $response["token_type"],
                        $_SESSION['expires_in']
                    );
                }
            }
        }
    }

    /* Fetch user's albums (50 max)*/

    $albums = $fb->get('/'.$_SESSION['user_id'].'/albums?fields=id,picture,name,count')->getGraphEdge();
    $albums_array = $albums->asArray();

    $albums = $fb->next($albums);
    $albums_array = array_merge($albums_array, $albums->asArray());
    //var_dump($albums_array); die();
    /*if(is_array($albums)){
        foreach ($albums as $key => $each){
            $photos = $fb->get('/'.$each['id'].'/photos?fields=id,picture,name&limit=50')->getDecodedBody();
        }
    }*/
    foreach ($albums_array as $key => $value) {
        if(($value['count'] == 0)){
            unset($albums_array[$key]);
        }
    }
    //$app->render('index.twig');
    $app->render('home.twig', array(
            'profile_picture'   => $img,
            'name'              => $_SESSION['user_name'],
            'albums'            => $albums_array
        ));

});

// This is nothing but a logout page
$app->get('/clearsession', function () use ($app) {
    /* Load and clear sessions */
    session_destroy();
     
    /* Redirect to page with the connect to Twitter option. */
    $app->redirect('/');
    exit;
});

// This is nothing but a logout page
$app->get('/sign-out', function () use ($app) {
    /* Load and clear sessions */
    session_destroy();
     
    /* Redirect to page with the connect to Twitter option. */
    $app->redirect('/');
    exit;
});

function recursiveRemoveDirectory($directory)
{
    foreach(glob("{$directory}/*") as $file)
    {
        if(is_dir($file)) { 
            recursiveRemoveDirectory($file);
        } else {
            unlink($file);
        }
    }
    rmdir($directory);
}

function recursiveCheckDirectoryName($directory, $name)
{
    foreach(glob("{$directory}/*") as $i => $file)
    {
        if(is_dir($file) and $file == $name) { 
            $prefix = substr($file, -1);
            if((int)$prefix !== 0){
                $file = substr($file, 0, -1);
            }
            $prefix = (int)$prefix+1;

            $name = recursiveCheckDirectoryName($directory, trim($file).' '.$prefix);
        } 
    }
    return $name;
}

function filename_safe($name) { 
    $except = array('\\', '/', ':', '*', '?', '"', '<', '>', '|', '.', '(', ')', ';'); 
    return str_replace($except, '', $name); 
}

function create_album($album_name = ''){
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

    //Setup our cURL options
    //Notice the last one where we pass in the authentication header
    $ch = curl_init();  
    $options = array(
                CURLOPT_URL=> $feedUrl,
                CURLOPT_SSL_VERIFYPEER=> false,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_VERBOSE => 0,
                CURLOPT_POST=> true,
                CURLOPT_RETURNTRANSFER=> true,
                CURLOPT_HEADER=> false,
                CURLOPT_FOLLOWLOCATION=> true,
                CURLOPT_POSTFIELDS=> $rawXml,
                CURLOPT_HTTPHEADER=> array('GData-Version:  2', $authHeader, 'Content-Type:  application/atom+xml')
            );
    curl_setopt_array($ch, $options);

    //This call will create the Picasa album.
    //The return value is XML with a bunch of information about the newly created album.
    $response   = curl_exec($ch);
    //print_r($response);
    $album      = simplexml_load_string($response);
    return $album;
}
function upload_photos_to_picasa($album_id = '', $photos_dir = ''){
    if ($album_id == '') {
        return false;
    }
    
    //This is the authentication header we'll need to pass with each successive call
    $authHeader = 'Authorization:  Bearer '.$_SESSION['g_access_token'].'"';
    foreach(glob("{$photos_dir}/*") as $file)
    {
        if(!is_dir($file)) { 
            echo $albumUrl = $album_id;
            $imgName = $_SERVER['DOCUMENT_ROOT'].'/'.$file;

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

            $header = array('GData-Version:  2', $authHeader, 'Content-Type: multipart/related; boundary=P4CpLdIHZpYqNn7;', 'Content-Length: ' . strlen($data), 'MIME-version: 1.0');

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

            $header = array('GData-Version:  2', $authHeader, 'Content-Type: image/jpeg', 'Content-Length: ' . $fileSize, 'Slug: cute_baby_kitten.jpg');
            $data = $imgData; //Make sure the image data is NOT Base64 encoded otherwise the upload will fail with a "Not an image" error

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

// GET request on /album/:id. Should be self-explaining. 
$app->group('/album', function () use ($app, $model, $fb){

    $app->get('/', function () use ($app, $model, $fb) {

        $album_id = $_GET['id'];
    
        $fb->setDefaultAccessToken($_SESSION['fb_access_token']);
        $photos = $fb->get('/'.$album_id.'?fields=id,picture,photos{source},name&limit=50')->getDecodedBody();
        
        foreach ($photos['photos']['data'] as $key => $each) {
            $photos['data'][$key]['picture'] = $each['source'];    
        }

        $app->contentType('application/json;charset=utf-8');
        echo json_encode($photos['data']);
 
    });

    
    $app->get('/download', function () use ($app, $model, $fb){

        $album_ids      = $_GET['id'];
        $time           = time();
        $user_album_dir = 'user_albums/'.$_SESSION['user_id'];

        $fb->setDefaultAccessToken($_SESSION['fb_access_token']);

        if (file_exists($user_album_dir)) {
            recursiveRemoveDirectory($user_album_dir);
        }

        $created_albums = array();
        foreach ($album_ids as $key => $album_id) {
            
            $photos = $fb->get('/'.$album_id.'?fields=id,picture,photos.limit(50){source,name},name&limit=1')->getDecodedBody();
            $photos['name'] = filename_safe($photos['name']);
            $unique_name = recursiveCheckDirectoryName($user_album_dir, $user_album_dir.'/'.$photos['name']);
            $unique_name = explode('/', $unique_name);
            $photos['name'] = trim(end($unique_name));
            
            if (!file_exists($user_album_dir.'/'.$photos['name'])) {
                mkdir($user_album_dir.'/'.$photos['name'], 0777, true);
            }
            $created_albums[] = $photos['name'];
            $folders[]      = $user_album_dir.'/'.$photos['name'];
            foreach ($photos['photos']['data'] as $key => $each) {
                $photos['data'][$key]['picture'] = $each['source'];    
                
                $fp = fopen($user_album_dir.'/'.$photos['name'].'/picture_'.($key+1).'.jpg', "w");
                $ch = curl_init($each['source']);
                curl_setopt($ch, CURLOPT_NOPROGRESS, false );
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_FILE, $fp);
                $success = curl_exec($ch);
                $curl_info = curl_getinfo($ch);
                curl_close($ch);
                fclose($fp);
            }
        }
        
        $folders = array_unique($folders);
        if (count($album_ids)>1) {
            $album_name = 'albums';
        } else {
            $album_name = 'album';
        }
        $zippy = Zippy::load();
        $archive = $zippy->create('user_albums/'.$time.$_SESSION['user_id'].'_'.$album_name.'.zip', $folders[0], $recurssive = true);
        unset($folders[0]);
        if(is_array($folders) and (count($folders)>0)){
            $archive->addMembers($folders,true);
        }
        
        if (!file_exists($user_album_dir)) {
            mkdir($user_album_dir, 0777, true);
        }

        $zip_path   = 'user_albums/'.$time.$_SESSION['user_id'].'_'.$album_name.'.zip';
        $dest       = $user_album_dir.'/'.$album_name.'.zip';
        copy($zip_path,$dest);
        
        //chdir('user_albums');
        //chmod($_SESSION['user_id'].'_'.'albums'.'.zip',0777);
        //unlink($_SESSION['user_id'].'_'.'albums'.'.zip');
        //chdir('..');
        /* Archive is generate. Delete the folders/albums from server*/
        foreach ($folders as $key => $folder) {
            recursiveRemoveDirectory($folder);
        }
        /*chdir('user_albums');
        chmod($time.$_SESSION['user_id'].'_'.$album_name.'.zip', 0777);
        sleep(1);
        unlink($time.$_SESSION['user_id'].'_'.$album_name.'.zip');
        chdir('..');*/
        $app->contentType('application/json;charset=utf-8');
        echo json_encode(array('download_link' => $user_album_dir.'/'.$album_name.'.zip'));
        
    });

    $app->get('/upload', function () use ($app, $model, $fb){

        $album_ids      = $_GET['id'];
        $time           = time();
        $user_album_dir = 'user_albums/'.$_SESSION['user_id'];

        $fb->setDefaultAccessToken($_SESSION['fb_access_token']);

        if (file_exists($user_album_dir)) {
            recursiveRemoveDirectory($user_album_dir);
        }

        $created_albums = array();
        foreach ($album_ids as $key => $album_id) {
            
            $photos = $fb->get('/'.$album_id.'?fields=id,picture,photos.limit(50){source,name},name&limit=1')->getDecodedBody();
            $photos['name'] = filename_safe($photos['name']);
            $unique_name = recursiveCheckDirectoryName($user_album_dir, $user_album_dir.'/'.$photos['name']);
            $unique_name = explode('/', $unique_name);
            $photos['name'] = trim(end($unique_name));
            
            if (!file_exists($user_album_dir.'/'.$photos['name'])) {
                mkdir($user_album_dir.'/'.$photos['name'], 0777, true);
            }
            $created_albums[] = $photos['name'];
            $folders[] = $user_album_dir.'/'.$photos['name'];
            $picasa_album   = create_album($photos['name']);
            
            $uri = '';
            foreach ($picasa_album->link as $entry) {
                if($uri == ''){
                    $uri = $entry->attributes()->{'href'}[0];
                }
            }
            foreach ($photos['photos']['data'] as $key => $each) {
                $photos['data'][$key]['picture'] = $each['source'];    
                
                $fp = fopen($user_album_dir.'/'.$photos['name'].'/picture_'.($key+1).'.jpg', "w");
                $ch = curl_init($each['source']);
                curl_setopt($ch, CURLOPT_NOPROGRESS, false );
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_FILE, $fp);
                $success = curl_exec($ch);
                $curl_info = curl_getinfo($ch);
                curl_close($ch);
                fclose($fp);
            }
            /* Upload copied photos to picasa album */
            $upload_photos = upload_photos_to_picasa($uri, $user_album_dir.'/'.$photos['name']);
        }
        
        $folders = array_unique($folders);
       
        /* Photos are uploaded. Delete the folders/albums from server*/
        foreach ($folders as $key => $folder) {
            recursiveRemoveDirectory($folder);
        }
        
        $app->contentType('application/json;charset=utf-8');
        echo json_encode(array('status' => 'success'));
        
    });
});



/******************************************* RUN THE APP *******************************************************/

$app->run();
