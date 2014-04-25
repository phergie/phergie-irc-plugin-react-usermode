<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link https://github.com/phergie/phergie-irc-plugin-react-usermode for the canonical source repository
 * @copyright Copyright (c) 2008-2014 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license New BSD License
 * @package Phergie\Irc\Plugin\React\UserMode
 */

namespace Phergie\Irc\Plugin\React\UserMode;

use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Bot\React\EventQueueInterface;
use Phergie\Irc\ConnectionInterface;
use Phergie\Irc\Event\EventInterface;
use Phergie\Irc\Event\UserEventInterface;

/**
 * Plugin for monitoring and providing access to user mode information.
 *
 * @category Phergie
 * @package Phergie\Irc\Plugin\React\UserMode
 */
class Plugin extends AbstractPlugin
{
    /**
     * Mapping of connection masks to channel names to user nicknames to mode
     * values to true values for active modes
     *
     * @var array
     */
    protected $modes = array();

    /**
     * Mapping of mode letters to corresponding nickname prefix characters
     *
     * @var array
     */
    protected $prefixes = array(
        '~' => 'q', // owner
        '&' => 'a', // admin
        '@' => 'o', // op
        '%' => 'h', // halfop
        '+' => 'v', // voice
    );

    /**
     * Accepts configuration.
     *
     * Supported keys:
     *
     * prefixes - optional mapping of nickname prefix characters to
     * corresponding mode letters, used for getting initial mode information
     * after joining a channel
     *
     * @param array $config
     */
    public function __construct(array $config = array())
    {
        if (isset($config['prefixes']) && is_array($config['prefixes'])) {
            $this->prefixes = $config['prefixes'];
        }
    }

    /**
     * Indicates that the plugin monitors events related to user identity and
     * presence and mode indications and changes.
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return array(
            'irc.received.mode' => 'changeUserMode',
            'irc.received.part' => 'removeUserFromChannel',
            'irc.received.quit' => 'removeUser',
            'irc.received.nick' => 'changeUserNick',
            'irc.received.rpl_namreply' => 'loadUserModes',
        );
    }

    /**
     * Monitors use mode changes.
     *
     * @param \Phergie\Irc\Event\EventInterface $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function changeUserMode(EventInterface $event, EventQueueInterface $queue)
    {
        $logger = $this->getLogger();

        $params = $event->getParams();
        $logger->debug('Changing user mode', array('params' => $params));

        // Disregard mode changes without both an associated channel and user
        if (!isset($params['channel']) || !isset($params['user'])) {
            $logger->debug('Missing channel or user, skipping');
            return;
        }

        $connectionMask = $this->getConnectionMask($event->getConnection());
        $channel = $params['channel'];
        $nick = $params['user'];
        $modes = str_split($params['mode']);
        $operation = array_shift($modes);

        $logger->debug('Extracted event data', array(
            'connectionMask' => $connectionMask,
            'channel' => $channel,
            'nick' => $nick,
            'operation' => $operation,
            'modes' => $modes,
        ));

        foreach ($modes as $mode) {
            switch ($operation) {
                case '+':
                    if (!isset($this->modes[$connectionMask])) {
                        $this->modes[$connectionMask] = array();
                    }
                    if (!isset($this->modes[$connectionMask][$channel])) {
                        $this->modes[$connectionMask][$channel] = array();
                    }
                    if (!isset($this->modes[$connectionMask][$channel][$nick])) {
                        $this->modes[$connectionMask][$channel][$nick] = array();
                    }
                    $this->modes[$connectionMask][$channel][$nick][$mode] = true;
                    break;
                case '-':
                    unset($this->modes[$connectionMask][$channel][$nick][$mode]);
                    break;
                default:
                    $logger->warning('Encountered unknown operation', array(
                        'operation' => $operation,
                    ));
                    break;
            }
        }
    }

    /**
     * Removes user mode data that's no longer needed.
     *
     * @param \Phergie\Irc\Event\EventInterface $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function removeUserFromChannel(UserEventInterface $event, EventQueueInterface $queue)
    {
        $logger = $this->getLogger();
        $connectionMask = $this->getConnectionMask($event->getConnection());
        $params = $event->getParams();
        $logger->debug('Removing user from channel', array('params' => $params));
        $this->removeUserData(
            $connectionMask,
            explode(',', $params['channels']),
            $event->getNick()
        );
    }

    /**
     * Removes user mode data that's no longer needed.
     *
     * @param \Phergie\Irc\Event\EventInterface $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function removeUser(UserEventInterface $event, EventQueueInterface $queue)
    {
        $logger = $this->getLogger();
        $logger->debug('Removing user from all channels');
        $connectionMask = $this->getConnectionMask($event->getConnection());
        $this->removeUserData(
            $connectionMask,
            array_keys($this->modes[$connectionMask]),
            $event->getNick()
        );
    }

    /**
     * Removes mode data for a user and list of channels.
     *
     * @param string $connectionMask
     * @param array $channels
     * @param string $nick
     */
    protected function removeUserData($connectionMask, array $channels, $nick)
    {
        $logger = $this->getLogger();
        foreach ($channels as $channel) {
            $logger->debug('Removing user mode data', array(
                'connectionMask' => $connectionMask,
                'channel' => $channel,
                'nick' => $nick,
            ));
            unset($this->modes[$connectionMask][$channel][$nick]);
        }
    }

    /**
     * Accounts for user nick changes in stored data.
     *
     * @param \Phergie\Irc\Event\EventInterface $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function changeUserNick(UserEventInterface $event, EventQueueInterface $queue)
    {
        $logger = $this->getLogger();

        $connectionMask = $this->getConnectionMask($event->getConnection());
        $old = $event->getNick();
        $params = $event->getParams();
        $new = $params['nickname'];
        $logger->debug('Changing user nick', array(
            'connectionMask' => $connectionMask,
            'oldNick' => $old,
            'newNick' => $new,
        ));

        foreach (array_keys($this->modes[$connectionMask]) as $channel) {
            if (!isset($this->modes[$connectionMask][$channel][$old])) {
                continue;
            }
            $logger->debug('Moving user mode data', array(
                'connectionMask' => $connectionMask,
                'channel' => $channel,
                'oldNick' => $old,
                'newNick' => $new,
            ));
            $this->modes[$connectionMask][$channel][$new] = $this->modes[$connectionMask][$channel][$old];
            unset($this->modes[$connectionMask][$channel][$old]);
        }
    }

    /**
     * Loads initial user mode data when the bot joins a channel.
     *
     * @param \Phergie\Irc\Event\EventInterface $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function loadUserModes(EventInterface $event, EventQueueInterface $queue)
    {
        $logger = $this->getLogger();

        $connectionMask = $this->getConnectionMask($event->getConnection());
        $params = $event->getParams();
        $channel = ltrim(array_shift($params), '=*@');
        $validPrefixes = implode('', array_keys($this->prefixes));
        $pattern = '/^([' . preg_quote($validPrefixes) . ']+)(.+)$/';
        $logger->debug('Gathering initial user mode data', array(
            'connectionMask' => $connectionMask,
            'channel' => $channel,
        ));

        foreach ($params as $fullNick) {
            if (!preg_match($pattern, $fullNick, $match)) {
                continue;
            }
            $nickPrefixes = str_split($match[1]);
            $nick = $match[2];
            foreach ($nickPrefixes as $prefix) {
                $mode = $this->prefixes[$prefix];
                $logger->debug('Recording user mode', array(
                    'connectionMask' => $connectionMask,
                    'channel' => $channel,
                    'nick' => $nick,
                    'mode' => $mode,
                ));
                $this->modes[$connectionMask][$channel][$nick][$mode] = true;
            }
        }
    }

    /**
     * Returns whether a user has a particular mode in a particular channel.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     * @param string $channel
     * @param string $nick
     * @param string $mode
     * @return boolean
     */
    public function userHasMode(ConnectionInterface $connection, $channel, $nick, $mode)
    {
        $connectionMask = $this->getConnectionMask($connection);
        return isset($this->modes[$connectionMask][$channel][$nick][$mode]);
    }

    /**
     * Returns a list of modes for a user in a particular channel.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     * @param string $channel
     * @param string $nick
     * @return array Enumerated array of mode letters or an empty array if the
     *         user has no modes in the specified channel
     */
    public function getUserModes(ConnectionInterface $connection, $channel, $nick)
    {
        $connectionMask = $this->getConnectionMask($connection);
        if (isset($this->modes[$connectionMask][$channel][$nick])) {
            return array_keys($this->modes[$connectionMask][$channel][$nick]);
        }
        return array();
    }

    /**
     * Returns the mask string for a given connection.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     * @return string
     */
    protected function getConnectionMask(ConnectionInterface $connection)
    {
        return strtolower(sprintf(
            '%s!%s@%s',
            $connection->getNickname(),
            $connection->getUsername(),
            $connection->getServerHostname()
        ));
    }
}
