<?php

namespace Renogen\Controller;

use Renogen\Base\Controller;
use Symfony\Component\HttpFoundation\Request;

class Auth extends Controller
{

    public function login(Request $request, $username = null)
    {
        $count_last = 30;
        $query = $this->app['em']->createQuery("SELECT COUNT(u) FROM \Renogen\Entity\User u WHERE u.last_login > ?1");
        $query->setParameter(1, new \DateTime("- $count_last minute"));
        $usersCount = $query->getSingleScalarResult();

        $message = array();
        if (($login_error = $this->app['security.last_error']($request))) {
            $message['text'] = $login_error;
            $message['negative'] = true;
        } elseif (!empty($username)) {
            $message['text'] = 'You have been successfully registered. Please login now to set your password.';
            $message['negative'] = false;
        }

        return $this->render("login", array(
                'message' => $message,
                'last_username' => $username ?: $this->app['session']->get('_security.last_username'),
                'bottom_message' => ($usersCount < 2 ? '' : "There are ${usersCount} users who logged in within the last ${count_last} minutes"),
                'self_register' => (count($this->app['datastore']->queryMany('\Renogen\Entity\AuthDriver', array(
                        'allow_self_registration' => 1))) > 0),
        ));
    }

    public function register(Request $request)
    {
        $recaptcha_keys = array(
            'sitekey' => getenv('RECAPTCHA_SITE_KEY'),
            'secretkey' => getenv('RECAPTCHA_SECRET_KEY'),
        );

        $ds = $this->app['datastore'];
        $post = $request->request;
        $selected_auth = null;
        $auths = $ds->queryMany('\Renogen\Entity\AuthDriver', array(
            'allow_self_registration' => 1));

        if (count($auths) == 0) {
            throw new \Exception("Self-registration is disabled");
        }

        $user = new \Renogen\Entity\User();
        if (count($auths) == 1) {
            $selected_auth = $auths[0];
        }

        if ($post->has('auth')) {
            foreach ($auths as $auth) {
                if ($post->get('auth') == $auth->name) {
                    $selected_auth = $auth;
                    break;
                }
            }
        }

        if ($post->get('_action') == 'Proceed to register') {
            if (!empty($recaptcha_keys['secretkey'])) {
                $recaptcha = new \ReCaptcha\ReCaptcha($recaptcha_keys['secretkey']);
                $resp = $recaptcha->verify($post->get('g-recaptcha-response'));
                if (!$resp->isSuccess()) {
                    $user->errors['recaptcha'] = 'Invalid response: '.join(", ", $resp->getErrorCodes());
                }
            }

            $user->roles = array('ROLE_USER');
            if ($ds->prepareValidateEntity($user, array('auth', 'username', 'shortname',
                    'email'), $post)) {
                $ds->commit($user);
                return $this->app->params_redirect('login_username', array('username' => $user->username));
            }
        }

        if ($selected_auth) {
            $user->auth = $selected_auth->name;
        }

        return $this->render("register_form", array(
                'auths' => $auths,
                'auth' => $selected_auth,
                'user' => $user,
                'errors' => $user->errors,
                'recaptcha' => $recaptcha_keys,
        ));
    }
}