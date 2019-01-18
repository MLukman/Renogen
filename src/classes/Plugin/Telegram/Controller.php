<?php

namespace Renogen\Plugin\Telegram;

class Controller extends \Renogen\Plugin\BaseController
{
    protected $twigpath;

    public function getTitle()
    {
        return 'Telegram Notification';
    }

    public function handleConfigure(\Symfony\Component\HttpFoundation\Request $request,
                                    \Renogen\Entity\Project $project,
                                    \Renogen\Plugin\BaseCore &$pluginCore)
    {
        $post    = array(
            'token' => null,
            'groups' => array(),
        );
        $options = $pluginCore->getOptions();
        if (isset($options['group_id']) && isset($options['group_name'])) {
            $post['groups'][$options['group_id']] = $options['group_name'];
        }
        if (($token = $request->request->get('bot_token'))) {
            $post['token'] = $token;
            $client        = new \GuzzleHttp\Client();
            $response      = $client->request('GET', "https://api.telegram.org/bot$token/getUpdates");
            $updates       = json_decode($response->getBody(), true);
            if (isset($updates['result'])) {
                foreach ($updates['result'] as $update) {
                    if ($update['message']['chat']['type'] == 'group') {
                        $post['groups'][$update['message']['chat']['id']] = $update['message']['chat']['title'];
                    }
                }
            }
            if (($group_id = $request->request->get('group_id'))) {
                $pluginCore->setOptions(array(
                    'bot_token' => $token,
                    'group_id' => $group_id,
                    'group_name' => $request->request->get('group_name')[$group_id],
                ));
                $this->savePlugin();
            }
        }
        return $this->render('admin', $post);
    }
}