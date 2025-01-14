<?php

namespace BotMan\Drivers\Discord;

use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Interfaces\DriverEventInterface;
use BotMan\BotMan\Interfaces\DriverInterface;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\BotMan\Users\User;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Illuminate\Support\Collection;
use React\Promise\PromiseInterface;

class DiscordDriver implements DriverInterface
{
    /** @var Message */
    protected $message;

    /** @var Discord */
    protected $client;

    /** @var string */
    protected $bot_id;

    const DRIVER_NAME = 'Discord';

    protected $file;

    /**
     * Driver constructor.
     *
     * @param  array  $config
     * @param  Discord  $client
     */
    public function __construct(array $config, Discord $client)
    {
        $this->event = Collection::make();
        $this->config = Collection::make($config);
        $this->client = $client;

        $this->client->on('message', function (Message $message) {
            $this->message = $message;
        });
    }

    /**
     * Connected event.
     */
    public function connected()
    {
        $this->bot_id = $this->client->user->tag;
    }

    /**
     * Return the driver name.
     *
     * @return string
     */
    public function getName()
    {
        return self::DRIVER_NAME;
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return false;
    }

    /**
     * @return bool|DriverEventInterface
     */
    public function hasMatchingEvent()
    {
        return false;
    }

    /**
     * @param  IncomingMessage  $message
     * @return Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        return Answer::create($this->message->content ?? null)->setMessage($message);
    }

    /**
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        $messageText = $this->message->content ?? null;
        $user_id = $this->message->author->id ?? null;
        $channel_id = $this->message->channel->id ?? null;

        $message = new IncomingMessage($messageText, $user_id, $channel_id, $this->message);
        $message->setIsFromBot($this->isBot());

        return [$message];
    }

    /**
     * @return bool
     */
    protected function isBot()
    {
        return $this->message->author->bot ?? false;;
    }

    /**
     * @param  string|\BotMan\BotMan\Messages\Outgoing\Question|IncomingMessage  $message
     * @param  IncomingMessage  $matchingMessage
     * @param  array  $additionalParameters
     * @return mixed
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        $payload = [
            'message' => '',
            'embed' => '',
        ];

        if ($message instanceof OutgoingMessage) {
            $payload['message'] = $message->getText();

            $attachment = $message->getAttachment();

            if (! is_null($attachment)) {
                if ($attachment instanceof Image) {
                    $payload['embed'] = [
                        'image' => [
                            'url' => $attachment->getUrl(),
                        ],
                    ];
                }
            }
        } else {
            $payload['message'] = $message;
        }

        return $payload;
    }

    /**
     * @param  mixed  $payload
     * @return PromiseInterface
     */
    public function sendPayload($payload)
    {
        if (empty($this->message)) {
            return null;
        }

        return $this->message->channel->sendMessage($payload['message'], false, $payload['embed']);
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return ! is_null($this->config->get('token'));
    }

    /**
     * Send a typing indicator.
     *
     * @param  IncomingMessage  $matchingMessage
     * @return mixed
     */
    public function types(IncomingMessage $matchingMessage)
    {
    }

    /**
     * Send a typing indicator and wait for the given amount of seconds.
     *
     * @param  IncomingMessage  $matchingMessage
     * @param  int  $seconds
     * @param  float  $seconds
     * @return mixed
     */
    public function typesAndWaits(IncomingMessage $matchingMessage, float $seconds)
    {
    }

    /**
     * Retrieve User information.
     *
     * @param  IncomingMessage  $matchingMessage
     * @return User
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        $user = null;
        $this->client->getUserById($matchingMessage->getSender())->then(function ($_user) use (&$user) {
            $user = $_user;
        });
        if (! is_null($user)) {
            return new User(
                $matchingMessage->getSender(),
                $user->getFirstName(),
                $user->getLastName(),
                $user->getUsername()
            );
        }

        return new User($this->message->author->id, '', '', $this->message->author->username);
    }

    /**
     * @return Discord
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param $endpoint
     * @param  array  $parameters
     * @param  IncomingMessage  $matchingMessage
     * @return \React\Promise\PromiseInterface
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
    }

    /**
     * Tells if the stored conversation callbacks are serialized.
     *
     * @return bool
     */
    public function serializesCallbacks()
    {
        return false;
    }

    /**
     * Load factory extensions.
     */
    public static function loadExtension()
    {
        $factory = new Factory();

        BotManFactory::extend('createForDiscord', [$factory, 'createForDiscord']);
        BotManFactory::extend('createUsingDiscord', [$factory, 'createUsingDiscord']);
    }
}
