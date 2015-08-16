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

$app->get('/callback', function () use ($app, $model, $fb) {
    $fb_cred = $app->config('fb');
    
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
        $is_user_exists = $model->getUser($user["id"]);

        if (!$is_user_exists) {
            $model->addUser(
                $user["id"],
                $user["name"],
                $user["email"]
            );
        }
        $app->redirect('/home');
    }
    
});

$app->get('/home', function () use ($app, $fb) {
    
    $fb_cred = $app->config('fb');

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
        foreach ($album_ids as $key => $album_id) {
            
            $photos = $fb->get('/'.$album_id.'?fields=id,picture,photos{source,name},name&limit=50')->getDecodedBody();

            if (!file_exists($user_album_dir.'/'.$photos['name'])) {
                mkdir($user_album_dir.'/'.$photos['name'], 0777, true);
            }
            $folders[] = $user_album_dir.'/'.$photos['name'];
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
        if (count($album_ids)>1) {
            $album_name = 'albums';
        } else {
            $album_name = 'album';
        }
        $zippy = Zippy::load();
        $archive = $zippy->create('user_albums/'.$time.$_SESSION['user_id'].'_'.$album_name.'.zip', $folders, $recurssive = true);
        
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
});

// // GET request on /download-albums.
$app->get('/download-albums', function () use ($app, $model, $fb){

    $album_id = $_GET['id'];
    
    $img= "https://graph.facebook.com/".$_SESSION['user_id']."/picture";
    $fb->setDefaultAccessToken($_SESSION['fb_access_token']);
    $photos = $fb->get('/'.$album_id.'?fields=id,picture,photos{source},name&limit=50')->getDecodedBody();
    
    foreach ($photos['photos']['data'] as $key => $each) {
        //$pic = $fb->get('/'.$each['id'].'?fields=images')->getDecodedBody();
        //print_r($pic['images'][0]['source']); die();
        $photos['data'][$key]['picture'] = $each['source'];    
    }
    /*echo "<pre>";
    print_r($photos); die();*/
    $app->contentType('application/json;charset=utf-8');
    echo json_encode($photos['data']);
    /*$app->render('album.twig', array(
            'profile_picture'   => $img,
            'photos' => $photos['data']
        ));*/
});


/******************************************* RUN THE APP *******************************************************/

$app->run();
