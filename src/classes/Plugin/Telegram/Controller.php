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
        $post       = array(
            'token' => null,
            'groups' => array(
                '' => '-- Disabled --',
            ),
        );
        $options    = $pluginCore->getOptions();
        $hasUpdates = false;
        if (isset($options['group_id']) && isset($options['group_name'])) {
            $post['groups'][$options['group_id']] = $options['group_name'];
        }
        if (($token = $request->request->get('bot_token') ?: (isset($options['bot_token'])
                ? $options['bot_token'] : null))) {
            $post['token'] = $token;
            $client        = new \GuzzleHttp\Client();
            $response      = $client->request('GET', "https://api.telegram.org/bot$token/getUpdates");
            $updates       = json_decode($response->getBody(), true);
            $time          = time();
            if (isset($updates['result'])) {
                $hasUpdates   = true;
                $lastUpdateId = null;
                foreach ($updates['result'] as $update) {
                    if ($update['message']['chat']['type'] == 'group') {
                        $post['groups'][$update['message']['chat']['id']] = $update['message']['chat']['title'];
                    }
                    if ($time - $update['message']['date'] > 3600) {
                        $lastUpdateId = $update['update_id'];
                    }
                }
                if ($lastUpdateId) {
                    $client->request('GET', "https://api.telegram.org/bot$token/getUpdates?offset=$lastUpdateId&timeout=1");
                }
            }
        }

        switch ($request->request->get('_action')) {
            case 'Save':
                $group_id    = $request->request->get('group_id');
                $group_names = $request->request->get('group_name');
                if (!$request->request->get('bot_token')) {
                    $this->deletePlugin();
                    return $this->app->redirect();
                } else if ($token) {
                    $pluginCore->setOptions(array(
                        'bot_token' => $token,
                        'group_id' => $group_id,
                        'group_name' => ($group_id && isset($group_names[$group_id]))
                                ? $group_names[$group_id] : null,
                    ));
                    $this->savePlugin();
                    return $this->app->redirect();
                }
                break;
        }
        return $this->render('admin', $post);
    }
}