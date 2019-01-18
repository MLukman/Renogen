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
        $token    = $this->options['bot_token'];
        $group_id = $this->options['group_id'];
        if (!$token || !$group_id) {
            return;
        }
        $client = new \GuzzleHttp\Client();
        $send   = $client->postAsync("https://api.telegram.org/bot$token/sendMessage", array(
            'json' => array(
                'chat_id' => $group_id,
                'text' => $message,
                'parse_mode' => 'html',
            )
        ));
        register_shutdown_function(function() use ($send) {
            $send->wait();
        });
    }

    protected function byWho()
    {
        return ($this->app->userEntity() ? "by ".$this->app->userEntity()->getName()
                : '');
    }

    protected function escape($text)
    {
        /*
          $text = str_replace("\\", "\\\\", $text);
          $text = str_replace("[", "\\[", $text);
          $text = str_replace("_", "\\_", $text);
          $text = str_replace("*", "\\*", $text);
          $text = str_replace("`", "\\`", $text);
         */
        return htmlentities($text);
    }

    public function onDeploymentCreated(\Renogen\Entity\Deployment $deployment)
    {
        $this->sendMessage(
            '<b>Deployment</b> <a href="'.$this->app->url('deployment_view', $this->app->entityParams($deployment)).'">'.$this->escape($deployment->title).'</a> has been created for <b>'.$deployment->datetimeString(true).'</b> '.$this->byWho()
        );
    }

    public function onDeploymentDateChanged(\Renogen\Entity\Deployment $deployment,
                                            \DateTime $old_date)
    {
        $this->sendMessage(
            '<b>Deployment</b> <a href="'.$this->app->url('deployment_view', $this->app->entityParams($deployment)).'">'.$this->escape($deployment->title).'</a> has changed date from <b>'.$old_date->format('d/m/Y h:i A').'</b> to <b>'.$deployment->execute_date->format('d/m/Y h:i A').'</b> '.$this->byWho()
        );
    }

    public function onItemStatusUpdated(\Renogen\Entity\Item $item,
                                        $old_status = null)
    {
        if ($old_status) {
            $this->sendMessage(
                '<b>Item</b> <a href="'.$this->app->url('item_view', $this->app->entityParams($item)).'">'.$this->escape($item->displayTitle()).'</a> has been changed status from <b>'.$old_status.'</b> to <b>'.$item->status.'</b> '.$this->byWho()
            );
        } else {
            $this->sendMessage(
                '<b>Item</b> <a href="'.$this->app->url('item_view', $this->app->entityParams($item)).'">'.$this->escape($item->displayTitle()).'</a> has been created for deployment <b>'.$this->escape($item->deployment->title).'</b> '.$this->byWho()
            );
        }
    }

    public function onItemDeleted(\Renogen\Entity\Item $item)
    {
        $this->sendMessage(
            '<b>Item</b> '.$this->escape($item->displayTitle()).' has been deleted from deployment <b>'.$this->escape($item->deployment->title).'</b> '.$this->byWho()
        );
    }
}