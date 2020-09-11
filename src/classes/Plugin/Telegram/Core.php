<?php

namespace Renogen\Plugin\Telegram;

use GuzzleHttp\Client;
use Renogen\Entity\Deployment;
use Renogen\Entity\Item;
use Renogen\Plugin\PluginCore;

class Core extends PluginCore
{
    protected $options = array(
        'bot_token' => null,
        'group_id' => null,
        'group_name' => null,
        'template_deployment_created' => '&#x1F4C5; [<b>{project}</b>] Deployment window <b>{datetime}</b> with title <a href="{url}">{title}</a> has been created by {who}',
        'template_deployment_date_changed' => '&#x1F4C5; [<b>{project}</b>] Deployment <a href="{url}">{title}</a> has changed date from <b>{old}</b> to <b>{new}</b> by {who}',
        'template_deployment_deleted' => '&#x1F4C5; [<b>{project}</b>] Deployment <b>{title} ({datetime})</b> has been deleted by {who}',
        'template_item_created' => '&#x1F4CC; [<b>{project}</b>] Item <a href="{url}">{title}</a> has been created for deployment <b>{deployment_title} ({deployment_datetime})</b> by {who}',
        'template_item_status_changed' => '&#x1F4CC; [<b>{project}</b>] Status of item <a href="{url}">{title}</a> has been changed from <b>{old}</b> to <b>{new}</b> by {who}',
        'template_item_moved' => '&#x1F4CC; [<b>{project}</b>] Item <a href="{url}">{title}</a> has moved from <b>{old_title} ({old_datetime})</b> to <b>{new_title} ({new_datetime})</b> by {who}',
        'template_item_deleted' => '&#x1F4CC; [<b>{project}</b>] Item <b>{title}</b> has been deleted from deployment <b>{deployment_title} ({deployment_datetime})</b> by {who}',
    );

    static public function getIcon()
    {
        return 'send';
    }

    static public function getTitle()
    {
        return "Telegram Notification";
    }

    protected function sendMessage($message)
    {
        if (empty($message) || substr($message, 0, 1) == '-') {
            // Not send if message template starts with a dash
            return;
        }
        $token = $this->options['bot_token'];
        $group_id = $this->options['group_id'];
        if (!$token || !$group_id) {
            return;
        }
        $client = new Client();
        $send = $client->postAsync("https://api.telegram.org/bot$token/sendMessage", array(
            'json' => array(
                'chat_id' => $group_id,
                'text' => $message,
                'parse_mode' => 'html',
            )
        ));
        register_shutdown_function(function() use ($send) {
            try {
                $send->wait();
            } catch (\Exception $ex) {
                // failed silently
            }
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
        $text = preg_replace_callback('/[\x{80}-\x{10FFFF}]/u', function ($m) {
            $char = current($m);
            $utf = iconv('UTF-8', 'UCS-4', $char);
            return sprintf("&#x%s;", ltrim(strtoupper(bin2hex($utf)), "0"));
        }, $text);
        return htmlentities($text, ENT_COMPAT | ENT_HTML401, null, false);
    }

    public function onDeploymentCreated(Deployment $deployment)
    {
        $message = $this->options['template_deployment_created'];
        $message = str_replace('{project}', $this->escape($deployment->project->title), $message);
        $message = str_replace('{url}', $this->escape($this->app->url('deployment_view', $this->app->entityParams($deployment))), $message);
        $message = str_replace('{title}', $this->escape($deployment->title), $message);
        $message = str_replace('{datetime}', $this->escape($deployment->datetimeString(true)), $message);
        $message = str_replace('{who}', $this->escape($deployment->created_by->shortname), $message);
        $message = str_replace('{bywho}', ' by '.$this->escape($deployment->created_by->shortname), $message);
        $this->sendMessage($message);
    }

    public function onDeploymentDateChanged(Deployment $deployment,
                                            \DateTime $old_date)
    {
        $message = $this->options['template_deployment_date_changed'];
        $message = str_replace('{project}', $this->escape($deployment->project->title), $message);
        $message = str_replace('{url}', $this->escape($this->app->url('deployment_view', $this->app->entityParams($deployment))), $message);
        $message = str_replace('{title}', $this->escape($deployment->title), $message);
        $message = str_replace('{old}', $this->escape($deployment->datetimeString(true, $old_date)), $message);
        $message = str_replace('{new}', $this->escape($deployment->datetimeString(true)), $message);
        $message = str_replace('{who}', $this->escape($deployment->updated_by->shortname), $message);
        $message = str_replace('{bywho}', ' by '.$this->escape($deployment->updated_by->shortname), $message);
        $this->sendMessage($message);
    }

    public function onDeploymentDeleted(Deployment $deployment)
    {
        $message = $this->options['template_deployment_deleted'];
        $message = str_replace('{project}', $this->escape($deployment->project->title), $message);
        $message = str_replace('{title}', $this->escape($deployment->title), $message);
        $message = str_replace('{datetime}', $this->escape($deployment->datetimeString(true)), $message);
        $message = str_replace('{who}', $this->escape($deployment->updated_by->shortname), $message);
        $message = str_replace('{bywho}', ' by '.$this->escape($deployment->updated_by->shortname), $message);
        $this->sendMessage($message);
    }

    public function onItemStatusUpdated(Item $item, $old_status = null)
    {
        if ($old_status) {
            $message = $this->options['template_item_status_changed'];
            $message = str_replace('{old}', $this->escape($old_status), $message);
            $message = str_replace('{new}', $this->escape($item->status), $message);
            $message = str_replace('{who}', $this->escape($item->updated_by->shortname), $message);
            $message = str_replace('{bywho}', ' by '.$this->escape($item->updated_by->shortname), $message);
        } else {
            $message = $this->options['template_item_created'];
            $message = str_replace('{who}', $this->escape($item->created_by->shortname), $message);
            $message = str_replace('{bywho}', ' by '.$this->escape($item->created_by->shortname), $message);
        }
        $message = str_replace('{project}', $this->escape($item->deployment->project->title), $message);
        $message = str_replace('{url}', $this->escape($this->app->url('item_view', $this->app->entityParams($item))), $message);
        $message = str_replace('{title}', $this->escape($item->displayTitle()), $message);
        $message = str_replace('{deployment}', $this->escape($item->deployment->title), $message);
        $message = str_replace('{deployment_title}', $this->escape($item->deployment->title), $message);
        $message = str_replace('{deployment_datetime}', $this->escape($item->deployment->datetimeString(true)), $message);
        $this->sendMessage($message);
    }

    public function onItemMoved(Item $item, Deployment $old_deployment)
    {
        $message = $this->options['template_item_moved'];
        $message = str_replace('{project}', $this->escape($item->deployment->project->title), $message);
        $message = str_replace('{url}', $this->escape($this->app->url('item_view', $this->app->entityParams($item))), $message);
        $message = str_replace('{title}', $this->escape($item->displayTitle()), $message);
        $message = str_replace('{old}', $this->escape($old_deployment->title), $message);
        $message = str_replace('{old_title}', $this->escape($old_deployment->title), $message);
        $message = str_replace('{old_datetime}', $this->escape($old_deployment->datetimeString(true)), $message);
        $message = str_replace('{new}', $this->escape($item->deployment->title), $message);
        $message = str_replace('{new_title}', $this->escape($item->deployment->title), $message);
        $message = str_replace('{new_datetime}', $this->escape($item->deployment->datetimeString(true)), $message);
        $message = str_replace('{who}', $this->escape($item->updated_by->shortname), $message);
        $message = str_replace('{bywho}', ' by '.$this->escape($item->updated_by->shortname), $message);
        $this->sendMessage($message);
    }

    public function onItemDeleted(Item $item)
    {
        $message = $this->options['template_item_deleted'];
        $message = str_replace('{project}', $this->escape($item->deployment->project->title), $message);
        $message = str_replace('{title}', $this->escape($item->displayTitle()), $message);
        $message = str_replace('{deployment}', $this->escape($item->deployment->title), $message);
        $message = str_replace('{deployment_title}', $this->escape($item->deployment->title), $message);
        $message = str_replace('{deployment_datetime}', $this->escape($item->deployment->datetimeString(true)), $message);
        $message = str_replace('{who}', $this->escape($item->updated_by->shortname), $message);
        $message = str_replace('{bywho}', ' by '.$this->escape($item->updated_by->shortname), $message);
        $this->sendMessage($message);
    }
}