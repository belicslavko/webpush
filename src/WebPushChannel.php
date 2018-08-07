<?php

namespace NotificationChannels\WebPush;

use App\Models\PrepareAutoresponder;
use App\Models\SentNotification;
use Minishlink\WebPush\WebPush;
use Illuminate\Notifications\Notification;

class WebPushChannel
{
    /** @var \Minishlink\WebPush\WebPush */
    protected $webPush;

    /**
     * @param  \Minishlink\WebPush\WebPush $webPush
     * @return void
     */
    public function __construct(WebPush $webPush)
    {
        $this->webPush = $webPush;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed $notifiable
     * @param  \Illuminate\Notifications\Notification $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        $subscriptions = $notifiable->routeNotificationFor('WebPush');

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode($notification->toWebPush($notifiable, $notification)->toArray());

        $notification_id = $notification->getNotificationId();
        $list_id = $notification->getListId();

        $subcriber_id = $notification->getSubscriberId();

        if(!empty($subcriber_id)){

	        $subscriptions->each( function ( $sub ) use ( $payload, $notification_id, $list_id, $subcriber_id ) {

	        	if($sub->id === (int)$subcriber_id) {
			        $this->webPush->sendNotification(
				        $sub->endpoint,
				        $payload,
				        $sub->public_key,
				        $sub->auth_token
			        );


			        $notify                        = new SentNotification();
			        $notify->push_subscriptions_id = $sub->id;
			        $notify->lists_id              = $list_id;
			        $notify->notifications_id      = $notification_id;
			        $notify->save();

			        PrepareAutoresponder::where('push_subscriptions_id', $sub->id)->where('notifications_id', $notification_id)->delete();
		        }

	        } );

        }else {

	        $subscriptions->each( function ( $sub ) use ( $payload, $notification_id, $list_id ) {
		        $this->webPush->sendNotification(
			        $sub->endpoint,
			        $payload,
			        $sub->public_key,
			        $sub->auth_token
		        );


		        $notify                        = new SentNotification();
		        $notify->push_subscriptions_id = $sub->id;
		        $notify->lists_id              = $list_id;
		        $notify->notifications_id      = $notification_id;
		        $notify->save();


	        } );

        }

        $response = $this->webPush->flush();

        $this->deleteInvalidSubscriptions($response, $subscriptions);
    }

    /**
     * @param  array|bool $response
     * @param  \Illuminate\Database\Eloquent\Collection $subscriptions
     * @return void
     */
    protected function deleteInvalidSubscriptions($response, $subscriptions)
    {
        if (! is_array($response)) {
            return;
        }

        foreach ($response as $index => $value) {
            if (! $value['success'] && isset($subscriptions[$index])) {
                $subscriptions[$index]->delete();
            }
        }
    }
}
