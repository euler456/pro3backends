<?php

require_once('../vendor/autoload.php');
require_once('./se.php');
require_once('./userfunction.php');

//sqsuser is from the userfunction.php which represent database
$sqsdb = new sqsuser;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\RedirectResponse;

$request = Request::createFromGlobals();
$response = new Response();
echo ("hrllo");
die;

$session = new Session(new NativeSessionStorage(), new AttributeBag());

$response->headers->set('Content-Type', 'application/json');
$response->headers->set('Access-Control-Allow-Headers', 'origin, content-type, accept');
$response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
$response->headers->set('Access-Control-Allow-Origin', 'https://proj3backends.herokuapp.com');
$response->headers->set('Access-Control-Allow-Credentials', 'true');
//put session here because here is the place the action started

ini_set('session.cookie_samesite',"None");
ini_set('session.cookie_secure', "1");
$session->start();
if (!$session->has('sessionObj')) {
    $session->set('sessionObj', new sqsSession);
}

$originPass= false;
if (strpos($request->headers->get('referer'), "proj3backends") == true) {
    $originPass = true; 
} else { 
    $response->setStatusCode(403);
    echo("ss");
    $response->send();
    return;
}
if ($originPass === true) { 
if (empty($request->query->all())) {
    $response->setStatusCode(400);
} elseif ($request->cookies->has('PHPSESSID')) {
    if ($session->get('sessionObj')->is_rate_limited()) {
        $response->setStatusCode(429);
    }
    if ($session->get('sessionObj')->day_rate_limited()) {
        $response->setStatusCode(429);
    }
    //if the request is post , the code will start the action which is in the POST Block
    if ($request->getMethod() == 'POST') {             // register
        if ($request->query->getAlpha('action') == 'register') {
            if ($request->request->has('username')) {
                $res = $sqsdb->userExists($request->request->get('username'));
                if ($res) {
                    $response->setStatusCode(418);
                } else {
                    if (
                        $request->request->has('username') and
                        $request->request->has('email') and
                        $request->request->has('phone') and
                        $request->request->has('postcode') and
                        $request->request->has('password') and
                        $request->request->has('password2')
                    ) {
                        $res = $session->get('sessionObj')->register(
                            $request->request->getAlpha('username'),
                            $request->request->get('email'),
                            $request->request->get('phone'),
                            $request->request->get('postcode'),
                            $request->request->get('password'),
                            $request->request->get('csrf')
                        );
                        if ($res === true) {
                            $response->setStatusCode(201);
                        } elseif ($res === false) {
                            $response->setStatusCode(403);
                        } elseif ($res === 0) {
                            $response->setStatusCode(500);
                        }
                    }
                }
            } else {
                $response->setStatusCode(400);
            }
        } elseif ($request->query->getAlpha('action') == 'login') {
            if ($request->request->has('username') and $request->request->has('password')) {
                $res = $session->get('sessionObj')->login(
                    $request->request->get('username'),
                    $request->request->get('password')
                );
                if ($res === false) {
                    $response->setContent(json_encode($request->request));
                    $response->setStatusCode(401);
                } elseif (count($res) == 1) {
                    $response->setStatusCode(203);
                    $response->setContent(json_encode($res));
                } elseif (count($res) > 1) {
                    $res = $session->get('sessionObj')->logEvent('Login');
                    $response->setStatusCode(200);
                    $response->setContent(json_encode($res));
                }
            } else {
                $response->setContent(json_encode($request));
                $response->setStatusCode(404);
            }
        } elseif ($request->query->getAlpha('action') == 'isloggedin') {
            $res = $session->get('sessionObj')->isLoggedIn();
            if ($res == false) {
                $response->setStatusCode(403);
            } elseif (count($res) == 1) {
                $response->setStatusCode(203);
                $response->setContent(json_encode($res));
            }
        } elseif ($request->query->getAlpha('action') == 'update') {
            $res = $session->get('sessionObj')->isLoggedIn();
            if (($request->request->has('username')) && ($res != false)) {
                $res = $sqsdb->userExists($request->request->get('username'));
                if ($res) {
                    $response->setStatusCode(400);
                } else {
                    if (
                        $request->request->has('currentusername') and
                        $request->request->has('username') and
                        $request->request->has('email') and
                        $request->request->has('phone') and
                        $request->request->has('postcode') and
                        $request->request->has('password') and
                        $request->request->has('password2')
                    ) {
                        $res = $session->get('sessionObj')->update(
                            //    $res = $sqsdb->userid($request->request->get('currentusername')),
                            $request->request->getAlpha('username'),
                            $request->request->get('email'),
                            $request->request->get('phone'),
                            $request->request->get('postcode'),
                            $request->request->get('password'),
                            $request->request->get('csrf')
                        );
                        if ($res === true) {
                            $res = $session->get('sessionObj')->logEvent('edit profile');
                            $response->setStatusCode(201);
                        } elseif ($res === false) {
                            $response->setStatusCode(403);
                        } elseif ($res === 0) {
                            $response->setStatusCode(500);
                        }
                    }
                }
            } else {
                $response->setStatusCode(400);
            }
        } elseif ($request->query->getAlpha('action') == 'displayfood') {
            $res = $session->get('sessionObj')->isLoggedIn();
            if ($res == false) {
                $response->setStatusCode(400);}
                else{
                $res = $session->get('sessionObj')->display();
                return $res;
            } 
        } elseif ($request->query->getAlpha('action') == 'addfood') {
            $res = $session->get('sessionObj')->isLoggedIn();
            if ($res == false) {
                $response->setStatusCode(400);}
                else{
                if (
                    $request->request->has('foodname') and
                    $request->request->has('price')   and
                    $request->request->has('description') and
                    $request->request->has('image') and
                    $request->request->has('options')
                ) {
                    $response->setStatusCode(201);
                    $res = $session->get('sessionObj')->addfood(
                        $request->request->get('foodname'),
                        $request->request->get('price'),
                        $request->request->get('description'),
                        $request->request->get('options'),
                        $request->request->get('image')
                    );
                    if ($res === true) {
                        $res = $session->get('sessionObj')->logEvent('addfood');
                        $response->setStatusCode(201);
                    } elseif ($res === false) {
                        $response->setStatusCode(403);
                    } elseif ($res === 0) {
                        $response->setStatusCode(500);
                    }
                } else {
                    $response->setStatusCode(400);
                }
            } 
        } elseif ($request->query->getAlpha('action') == 'deleteFOOD') {
            $res = $session->get('sessionObj')->isLoggedIn();
            if ($res === false) {
                $response->setStatusCode(400);}
                else{
                $res = $session->get('sessionObj')->deleteFOOD(
                    $request->request->get('F_ID')
                );
                if ($res === true) {
                    $res = $session->get('sessionObj')->logEvent('deletefood');
                    $response->setStatusCode(201);
                } elseif ($res === false) {
                    $response->setStatusCode(403);
                } elseif ($res === 0) {
                    $response->setStatusCode(500);
                }
            } 
        } elseif ($request->query->getAlpha('action') == 'updatefood') {
            $res = $session->get('sessionObj')->isLoggedIn();
            if ($res === false) {
                $response->setStatusCode(400);}
                else{
                if (
                    $request->request->has('F_ID') and
                    $request->request->has('foodname') and
                    $request->request->has('price')   and
                    $request->request->has('description') and
                    $request->request->has('image') and
                    $request->request->has('options')
                ) {
                    $res = $session->get('sessionObj')->updatefood(
                        $request->request->get('F_ID'),
                        $request->request->get('foodname'),
                        $request->request->get('price'),
                        $request->request->get('description'),
                        $request->request->get('options'),
                        $request->request->get('image')
                    );
                    if ($res === true) {
                        $response->setStatusCode(201);
                        $res = $session->get('sessionObj')->logEvent('updatefood');
                    } elseif ($res === false) {
                        $response->setStatusCode(403);
                    } elseif ($res === 0) {
                        $response->setStatusCode(500);
                    }
                } else {
                    $response->setStatusCode(400);
                }
            } 
        } 
        
        
        
        
        elseif ($request->query->getAlpha('action') == 'displayuser') {
            $res = $session->get('sessionObj')->isLoggedIn();
            if ($res == false) {
                $response->setStatusCode(400);}
                else{
                $res = $session->get('sessionObj')->displayuser();
                return $res;
            } 
        } elseif ($request->query->getAlpha('action') == 'adduser') {
            $res = $session->get('sessionObj')->isLoggedIn();
            if ($res == false) {
                $response->setStatusCode(400);}
                else{
                if (
                    $request->request->has('username') and
                    $request->request->has('email') and
                    $request->request->has('phone') and
                    $request->request->has('postcode') and
                    $request->request->has('password') and
                    $request->request->has('usertype')
                ){
                    $response->setStatusCode(201);
                    $res = $session->get('sessionObj')->adduser(
                        $request->request->getAlpha('username'),
                        $request->request->get('email'),
                        $request->request->get('phone'),
                        $request->request->get('postcode'),
                        $request->request->get('password'),
                        $request->request->get('usertype')
                    );
                    if ($res === true) {
                        $res = $session->get('sessionObj')->logEvent('adduser');
                        $response->setStatusCode(201);
                    } elseif ($res === false) {
                        $response->setStatusCode(403);
                    } elseif ($res === 0) {
                        $response->setStatusCode(500);
                    }
                } else {
                    $response->setStatusCode(400);
                }
            } 
        } elseif ($request->query->getAlpha('action') == 'deleteuser') {
            $res = $session->get('sessionObj')->isLoggedIn();
            if ($res === false) {
                $response->setStatusCode(400);}
                else{
                $res = $session->get('sessionObj')->deleteuser(
                    $request->request->get('CustomerID')
                );
                if ($res === true) {
                    $res = $session->get('sessionObj')->logEvent('deleteuser');
                    $response->setStatusCode(201);
                } elseif ($res === false) {
                    $response->setStatusCode(403);
                } elseif ($res === 0) {
                    $response->setStatusCode(500);
                }
            } 
        } elseif ($request->query->getAlpha('action') == 'updateuser') {
            $res = $session->get('sessionObj')->isLoggedIn();
            if ($res === false) {
                $response->setStatusCode(400);}
                else{
                if (
                    $request->request->has('CustomerID') and
                    $request->request->has('username') and
                    $request->request->has('email') and
                    $request->request->has('phone') and
                    $request->request->has('postcode') and
                    $request->request->has('password') and 
                    $request->request->has('usertype')
                ) {
                    $res = $session->get('sessionObj')->updateuser(
                        $request->request->get('CustomerID'),
                        $request->request->getAlpha('username'),
                            $request->request->get('email'),
                            $request->request->get('phone'),
                            $request->request->get('postcode'),
                            $request->request->get('password'),
                            $request->request->get('usertype')
                    );
                    if ($res === true) {
                        $response->setStatusCode(201);
                        $res = $session->get('sessionObj')->logEvent('updateuser');
                    } elseif ($res === false) {
                        $response->setStatusCode(403);
                    } elseif ($res === 0) {
                        $response->setStatusCode(500);
                    }
                } else {
                    $response->setStatusCode(400);
                }
            } 
        }
        
        
        
        
        
        
        
        else {
            $response->setStatusCode(400);
        }
    }
    //if the request from the front-end JS is GET , the code will start the action which is in the GET Block
    if ($request->getMethod() == 'GET') {
        if ($request->query->getAlpha('action') == 'accountexists') {
            if ($request->query->has('username')) {
                $res = $sqsdb->userExists($request->query->get('username'));
                if ($res) {
                    $response->setStatusCode(400);
                } else {
                    $response->setStatusCode(204);
                }
            }
        } elseif ($request->query->getAlpha('action') == 'logout') {
            $res = $session->get('sessionObj')->logEvent('logout');
            $session->get('sessionObj')->logout();
            $response->setStatusCode(200);
        } elseif ($request->query->getAlpha('action') == 'orderID') {
            $res = $session->get('sessionObj')->orderID();
        }
    }
    if ($request->getMethod() == 'DELETE') {           // delete queue, delete comment
        $response->setStatusCode(400);
    }
    if ($request->getMethod() == 'PUT') {              // enqueue, add comment
        $response->setStatusCode(400);
    }
} else {
    $redirect = new RedirectResponse($_SERVER['REQUEST_URI']);
}

// Do logging just before sending response?

$response->send();
}