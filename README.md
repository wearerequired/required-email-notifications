Email Notifications
==============================
This is a WordPress plugin to handle email notifications with a easy to use API. It's extensible with custom adapters (email providers).
Notifications will be send via WordPress cron job, triggered every hour.

Email Providers
----------------
### Madrill ###
Mandrill provides a powerful API, built by the folks at MailChimp it's a good working service. You need a API-Key which you have to add to the plugin options, otherwise this adapter will not work.

Example Usage
-------------
#### Add Notifications ####
```php
req_notifications()->addNotification()
    // we just support the Mandrill adapter at this time, probably others will follow
    ->setAdapter( 'Mandrill' )
    ->setSubject( 'r+ notification plugin on Fire' )
    // the body has to be a string, could be html
    ->setBody( 'My Super-Duper-Body Text.' )
    // more recipients are possible
    ->addRecipient( 'stefan@required.ch', 'Stefan' )
    ->addRecipient( 'silvan@required.ch', 'Silvan' )

    // the following is optional
    ->addCcRecipient( 'email@example.com', 'Example Mail' )
    // just one bcc recipient is possible
    ->setBcc( 'blind-carbon@copy.com' )
    // more than one attachment is possible, just call this method how often you like
    ->addAttachment( '/path/to/my/attachment.ext' )
    // this is the default state which will be set automatically
    ->setState( \Rplus\Notifications\NotificationState::ISNEW )
    // schedule is optional, when set, mail will be sent at this time
    // (or when the next cron job runs, after this time)
    ->setSchedule( date('Y-m-d H:i:s', strtotime( '+1 day' )) )
    // this could be defined in plugin options and optional be overridden here
    ->setSender( 'sender@example.com', 'The sender' )

    // save this notification
    ->save();
```

#### Update Notifications ####
```php
// Update the subject
req_notifications()->getNotification( 123 /* The notification id */ )
    ->setSubject( 'I\'m a awesome notification. Trust me!' )
    ->save();
```
```php
// Add another recipient
req_notifications()->getNotification( 123 /* The notification id */ )
    ->addRecipient( 'recipient@email.com' )
    ->save();
```
