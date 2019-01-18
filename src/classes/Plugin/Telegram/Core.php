<?php

namespace Renogen\Plugin\Telegram;

class Core extends \Renogen\Plugin\BaseCore
{

    public function getIcon()
    {
        return 'send';
    }

    public function getPluginTitle()
    {
        return "Telegram Notification";
    }

    protected function sendMessage($message)
    {
        $token  = $this->options['bot_token'];
        $client = new \GuzzleHttp\Client();
        $client->postAsync("https://api.telegram.org/bot$token/sendMessage", array(
            'json' => array(
                'chat_id' => $this->options['group_id'],
                'text' => $message,
                'parse_mode' => 'markdown',
            )
        ))->wait();
    }

    public function onDeploymentCreated(\Renogen\Entity\Deployment $deployment)
    {
        $this->sendMessage(
            "*Deployment* [{$deployment->title}](".$this->app->url('deployment_view', $this->app->entityParams($deployment)).") has been created for *".$deployment->datetimeString(true)."*"
        );
    }

    public function onItemStatusUpdated(\Renogen\Entity\Item $item,
                                        $old_status = null)
    {
        if ($old_status) {
            $this->sendMessage(
                "*Item* [".$item->displayTitle()."](".$this->app->url('item_view', $this->app->entityParams($item)).") changed status from *".$old_status."* to *{$item->status}* for deployment *{$item->deployment->title}* by ".$this->app->userEntity()->getName()
            );
        } else {
            $this->sendMessage(
                "*Item* [".$item->displayTitle()."](".$this->app->url('item_view', $this->app->entityParams($item)).") has been created for deployment *{$item->deployment->title}* by ".$this->app->userEntity()->getName()
            );
        }
    }

    public function onItemDeleted(\Renogen\Entity\Item $item)
    {
        $this->sendMessage(
            "*Item* ".$item->displayTitle()." has been deleted from deployment *{$item->deployment->title}* by ".$this->app->userEntity()->getName()
        );
    }

    public function onDeploymentDateChanged(\Renogen\Entity\Deployment $deployment,
                                            \DateTime $old_date)
    {
        $this->sendMessage(
            "*Deployment* [{$deployment->title}](".$this->app->url('deployment_view', $this->app->entityParams($deployment)).") has changed date from *".$old_date->format('d/m/Y h:i A')."* to *".$deployment->execute_date->format('d/m/Y h:i A')."* by ".$this->app->userEntity()->getName()
        );
    }
}