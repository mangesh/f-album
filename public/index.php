<?php
/******************************* LOADING & INITIALIZING BASE APPLICATION ***************************************/
// Configuration for error reporting, useful to show every little problem during development
include 'inc/helper-functions.php';
error_reporting(E_ALL);
ini_set("display_errors", 1);
set_time_limit(0);
session_start();

// Load Composer's PSR-4 autoloader (necessary to load Slim, Mini etc.)
require '../lib/vendor/autoload.php';

use Alchemy\Zippy\Zippy;

// Initialize Slim (the router/micro framework used)
$app = new \Slim\Slim(array(
    'mode' => 'production'
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

    include 'inc/config.php';
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
    include 'inc/config.php';
    $app->config(array(
        'debug' => false,
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

// Process the google callback request
$app->get('/google_callback', function () use ($app, $model, $fb) {
    $google_cred = $app->config('google');
    
    if (empty($_SESSION['fb_access_token'])) {
        $app->redirect('/clearsession');
        exit;
    }
    
    if (isset($_GET['code'])) {
        $clientId       = $google_cred['client_id'];
        $clientSecret   = $google_cred['client_secret'];
        $referer        = current_page_url().'/home';

        $postBody = 'code='.urlencode($_GET['code'])
                  .'&grant_type=authorization_code'
                  .'&redirect_uri='.urlencode($google_cred['callback_url'])
                  .'&client_id='.urlencode($google_cred['client_id'])
                  .'&client_secret='.urlencode($google_cred['client_secret']);

        $curl = curl_init();
        curl_setopt_array(
            $curl,
            array( CURLOPT_CUSTOMREQUEST => 'POST'
               , CURLOPT_URL => 'https://accounts.google.com/o/oauth2/token'
               , CURLOPT_HTTPHEADER => array( 'Content-Type: application/x-www-form-urlencoded'
                                             , 'Content-Length: '.strlen($postBody)
                                             , 'User-Agent: f-album/0.1 +'.$referer
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
        
        if ($http_code === 200 && (isset($_SESSION['user_id']))) {
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
        $app->render('g_callback.twig');
    } else {
        $app->redirect('/?error=code-not-found');
    }
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
    //var_dump($user); die();
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
        } else {
            //print_r($is_user_exists); echo time(); die();
            $is_user_exists = (array)$is_user_exists;
            if (isset($is_user_exists['g_access_token']) && $is_user_exists['g_access_token'] <> '') {
                if ($is_user_exists['expires_in'] < time()) {
                    $refresh = update_token($google_cred, $is_user_exists['refresh_token']);
                    if ($refresh) {
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
                } else {
                    $_SESSION['g_access_token']     = $is_user_exists["g_access_token"];
                    $_SESSION['refresh_token']      = $is_user_exists['refresh_token'];
                    $_SESSION['expires_in']         = $is_user_exists["expires_in"];
                }
            }
        }
        $app->redirect('/home');
    }
    
});

$app->get('/home', function () use ($app, $model, $fb) {
    
    //$app->etag('unique-id');
    //$app->lastModified(1286139652);
    

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
    $permissions = $fb->get('/'.$_SESSION['user_id'].'/permissions')->getGraphEdge();
    //print_r($permissions); die();
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

    $is_user_exists = $model->getUser($_SESSION['user_id']);
    
    if ($is_user_exists) {
        $is_user_exists = (array)$is_user_exists;
        if (isset($is_user_exists['g_access_token']) && $is_user_exists['g_access_token'] <> '') {
            if ($is_user_exists['expires_in'] < time()) {
                $refresh = update_token($google_cred, $is_user_exists['refresh_token']);
                if ($refresh) {
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

    /* Fetch user's albums (50 max)*/

    $albums = $fb->get('/'.$_SESSION['user_id'].'/albums?fields=id,picture,name,count&limit=50')->getGraphEdge();
    //
    $albums_array = $albums->asArray();
    $meta_data = $albums->getMetaData();
    if(isset($metadata['paging']['next'])){
        $albums = $fb->next($albums);
        $albums_array = array_merge($albums_array, $albums->asArray());
    }
    
    foreach ($albums_array as $key => $value) {
        if (($value['count'] == 0)) {
            unset($albums_array[$key]);
        }
    }
    $app->etag('unique-resource-id');
    $app->expires('+1 day');
    $app->render('home.twig', array(
            'profile_picture'   => $img,
            'name'              => $_SESSION['user_name'],
            'albums'            => $albums_array
        ));
});

// Error Handler for any uncaught exception
// -----------------------------------------------------------------------------
// This can be silenced by turning on Slim Debugging. All exceptions thrown by
// our application will be collected here.
$app->error(function (\Exception $e) use ($app) {
    $app->render('error.twig', array(
        'message' => $e->getMessage()
    ), 500);
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

// GET request on /album/:id. Should be self-explaining. 
$app->group('/album', function () use ($app, $model, $fb) {

    $app->get('/', function () use ($app, $model, $fb) {

        $album_id = $_GET['id'];
    
        $fb->setDefaultAccessToken($_SESSION['fb_access_token']);
        $all_photos = $fb->get('/'.$album_id.'/photos?fields=id,photos,source,name&limit=1000');
        $photos = $all_photos->getGraphEdge()->asArray();

        foreach ($photos as $key => $each) {
            $photos['data'][$key]['picture'] = $each['source'];
        }
        foreach ($photos['photos']['data'] as $key => $each) {
            $photos['data'][$key]['picture'] = $each['source'];
        }

        $app->contentType('application/json;charset=utf-8');
        echo json_encode($photos['data']);
 
    });

    
    $app->get('/download', function () use ($app, $model, $fb) {

        $album_ids      = $_GET['id'];
        $time           = time();
        $user_album_dir = 'user_albums/'.$_SESSION['user_id'];

        $fb->setDefaultAccessToken($_SESSION['fb_access_token']);

        if (file_exists($user_album_dir)) {
            recursive_remove_directory($user_album_dir);
        }

        $created_albums = $folders = array();
        foreach ($album_ids as $key => $album_id) {
            $album = $fb
                ->get('/'.$album_id.'?fields=id,name')
                ->getDecodedBody();
            $photos = $fb
                ->get('/'.$album_id.'/photos?fields=id,photos,source,name&limit=1000')
                ->getGraphEdge()
                ->asArray();
            $album['name'] = filename_safe($album['name']);
            $unique_name = recursive_check_directory_name($user_album_dir, $user_album_dir.'/'.$album['name']);
            $unique_name = explode('/', $unique_name);
            $album['name'] = trim(end($unique_name));
            
            if (!file_exists($user_album_dir.'/'.$album['name'])) {
                mkdir($user_album_dir.'/'.$album['name'], 0777, true);
            }
            $created_albums[] = $album['name'];
            $folders[]      = $user_album_dir.'/'.$album['name'];
            foreach ($photos as $key => $each) {
                $photos['data'][$key]['picture'] = $each['source'];
                $fp = fopen($user_album_dir.'/'.$album['name'].'/picture_'.($key+1).'.jpg', "w");
                $ch = curl_init($each['source']);
                curl_setopt($ch, CURLOPT_NOPROGRESS, false);
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
        $archive = $zippy
            ->create('user_albums/'.$time.$_SESSION['user_id'].'_'.$album_name.'.zip', $folders[0], $recurssive = true);
        unset($folders[0]);
        if (is_array($folders) && (count($folders)>0)) {
            $archive->addMembers($folders, true);
        }
        
        if (!file_exists($user_album_dir)) {
            mkdir($user_album_dir, 0777, true);
        }
        $zippy = Zippy::load();
        $archive = $zippy
            ->create('user_albums/'.$time.$_SESSION['user_id'].'_'.$album_name.'.zip', $folders[0], $recurssive = true);
        unset($folders[0]);
        if (is_array($folders) && (count($folders)>0)) {
            $archive->addMembers($folders, true);
        }
        
        if (!file_exists($user_album_dir)) {
            mkdir($user_album_dir, 0777, true);
        }

        $zip_path   = 'user_albums/'.$time.$_SESSION['user_id'].'_'.$album_name.'.zip';
        $dest       = $user_album_dir.'/'.$album_name.'.zip';
        copy($zip_path, $dest);
        
        /*chdir('user_albums');
        chmod($_SESSION['user_id'].'_'.'albums'.'.zip',0777);
        unlink($_SESSION['user_id'].'_'.'albums'.'.zip');
        chdir('..');*/
        /* Archive is generate. Delete the folders/albums from server*/
        foreach ($folders as $key => $folder) {
            recursive_remove_directory($folder);
        }
        /*chdir('user_albums');
        chmod($time.$_SESSION['user_id'].'_'.$album_name.'.zip', 0777);
        sleep(1);
        unlink($time.$_SESSION['user_id'].'_'.$album_name.'.zip');
        chdir('..');*/
        $app->contentType('application/json;charset=utf-8');
        echo json_encode(array('download_link' => $user_album_dir.'/'.$album_name.'.zip'));
        
    });

    $app->get('/upload', function () use ($app, $model, $fb) {

        $is_user_exists = $model->getUser($_SESSION['user_id']);
    
        if ($is_user_exists) {
            $is_user_exists = (array) $is_user_exists;
            if ($is_user_exists['g_access_token'] == '') {
                $app->contentType('application/json;charset=utf-8');
                echo json_encode(array('status' => 'need_google_login'));
                exit();
            }
        }

        $album_ids      = $_GET['id'];
        $time           = time();
        $user_album_dir = 'user_albums/'.$_SESSION['user_id'];

        $fb->setDefaultAccessToken($_SESSION['fb_access_token']);

        if (file_exists($user_album_dir)) {
            recursive_remove_directory($user_album_dir);
        }

        $created_albums = $folders = array();
        foreach ($album_ids as $key => $album_id) {
            $album = $fb
                ->get('/'.$album_id.'?fields=id,name')
                ->getDecodedBody();
            $photos = $fb
                ->get('/'.$album_id.'/photos?fields=id,photos,source,name&limit=1000')
                ->getGraphEdge()
                ->asArray();
            $album['name'] = filename_safe($album['name']);
            $unique_name = recursive_check_directory_name($user_album_dir, $user_album_dir.'/'.$album['name']);
            $unique_name = explode('/', $unique_name);
            $album['name'] = trim(end($unique_name));
            
            if (!file_exists($user_album_dir.'/'.$album['name'])) {
                mkdir($user_album_dir.'/'.$album['name'], 0777, true);
            }
            $created_albums[] = $album['name'];
            $folders[] = $user_album_dir.'/'.$album['name'];
            $picasa_album   = create_album($album['name']);
            
            $uri = '';
            foreach ($picasa_album->link as $entry) {
                if ($uri == '') {
                    $uri = $entry->attributes()->{'href'}[0];
                }
            }
            foreach ($photos as $key => $each) {
                $photos['data'][$key]['picture'] = $each['source'];
                
                $fp = fopen($user_album_dir.'/'.$album['name'].'/picture_'.($key+1).'.jpg', "w");
                $ch = curl_init($each['source']);
                curl_setopt($ch, CURLOPT_NOPROGRESS, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_FILE, $fp);
                $success = curl_exec($ch);
                $curl_info = curl_getinfo($ch);
                curl_close($ch);
                fclose($fp);
            }
            /* Upload copied photos to picasa album */
            $upload_photos = upload_photos_to_picasa($uri, $user_album_dir.'/'.$album['name']);
        }
        
        $folders = array_unique($folders);
       
        /* Photos are uploaded. Delete the folders/albums from server*/
        foreach ($folders as $key => $folder) {
            recursive_remove_directory($folder);
        }
        
        $app->contentType('application/json;charset=utf-8');
        echo json_encode(array('status' => 'success'));
        
    });
});
/******************************************* RUN THE APP *******************************************************/
$app->run();
