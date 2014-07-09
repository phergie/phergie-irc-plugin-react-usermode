<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link https://github.com/phergie/phergie-irc-plugin-react-usermode for the canonical source repository
 * @copyright Copyright (c) 2008-2014 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license New BSD License
 * @package Phergie\Irc\Plugin\React\UserMode
 */

namespace Phergie\Irc\Tests\Plugin\React\UserMode;

use Phake;
use Phergie\Irc\Bot\React\EventQueueInterface;
use Phergie\Irc\Event\EventInterface;
use Phergie\Irc\Plugin\React\UserMode\Plugin;

/**
 * Tests for the Plugin class.
 *
 * @category Phergie
 * @package Phergie\Irc\Plugin\React\UserMode
 */
class PluginTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Instance of the class under test
     *
     * @var \Phergie\Irc\Plugin\React\UserMode\Plugin
     */
    protected $plugin;

    /**
     * Mock logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Mock event queue
     *
     * @var \Phergie\Irc\Bot\React\EventQueueInterface
     */
    protected $queue;

    /**
     * Instantiates the class under test.
     */
    protected function setUp()
    {
        $this->plugin = new Plugin;

        $this->logger = Phake::mock('\Psr\Log\LoggerInterface');
        $this->plugin->setLogger($this->logger);

        $this->queue = Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
    }

    /**
     * Data provider for testChangeUserModeDisregardsEvents().
     *
     * @return array
     */
    public function dataProviderChangeUserModeDisregardsEvents()
    {
        $data = array();

        // User without channel
        $data[] = array(array('user' => 'nick'));

        // Channel without user
        $data[] = array(array('channel' => '#channel'));

        // Neither channel nor user
        $data[] = array(array());

        return $data;
    }

    /**
     * Tests changeUserMode() with events it will disregard.
     *
     * @param array $params Event parameters
     * @dataProvider dataProviderChangeUserModeDisregardsEvents
     */
    public function testChangeUserModeDisregardsEvents(array $params)
    {
        $event = $this->getMockEvent();
        Phake::when($event)->getParams()->thenReturn($params);

        $this->plugin->changeUserMode($event, $this->queue);

        Phake::verify($this->logger)->debug('Missing channel or user, skipping');
    }

    /**
     * Returns a mock user mode event.
     *
     * @param string $operation
     * @param string $channel
     * @param string $nick
     * @return \Phergie\Irc\Event\EventInterface
     */
    protected function getMockUserModeEvent($operation = '+', $channel = '#channel', $nick = 'nick')
    {
        $mode = 'o';
        $params = array(
            'channel' => $channel,
            'user' => $nick,
            'mode' => $operation . $mode,
        );
        $connection = $this->getMockConnection();
        $event = $this->getMockEvent();
        Phake::when($event)->getParams()->thenReturn($params);
        Phake::when($event)->getConnection()->thenReturn($connection);
        return $event;
    }

    /**
     * Tests changeUserMode() adding a mode.
     */
    public function testChangeUserModeAddsMode()
    {
        $event = $this->getMockUserModeEvent();

        $this->plugin->changeUserMode($event, $this->queue);

        $this->assertTrue($this->plugin->userHasMode($event->getConnection(), '#channel', 'nick', 'o'));
    }

    /**
     * Tests changeUserMode() removing a mode.
     */
    public function testChangeUserModeRemovesMode()
    {
        $this->testChangeUserModeAddsMode();

        $event = $this->getMockUserModeEvent('-');

        $this->plugin->changeUserMode($event, $this->queue);

        $this->assertFalse($this->plugin->userHasMode($event->getConnection(), '#channel', 'nick', 'o'));
    }

    /**
     * Tests changeUserMode() with an unknown operation.
     */
    public function testChangeUserModeWithUnknownOperation()
    {
        $operation = '~';
        $event = $this->getMockUserModeEvent($operation);

        $this->plugin->changeUserMode($event, $this->queue);

        $this->assertFalse($this->plugin->userHasMode($event->getConnection(), '#channel', 'nick', 'o'));
        Phake::verify($this->logger)->warning(
            'Encountered unknown operation',
            array('operation' => $operation)
        );
    }

    /**
     * Tests removeUserFromChannel().
     */
    public function testRemoveUserFromChannel()
    {
        $this->testChangeUserModeAddsMode();

        $channel = '#channel';
        $nick = 'nick';
        $mode = 'o';
        $params = array(
            'channels' => $channel,
        );
        $connection = $this->getMockConnection();
        $event = $this->getMockUserEvent();
        Phake::when($event)->getParams()->thenReturn($params);
        Phake::when($event)->getConnection()->thenReturn($connection);
        Phake::when($event)->getNick()->thenReturn($nick);

        $this->plugin->removeUserFromChannel($event, $this->queue);

        $this->assertFalse($this->plugin->userHasMode($connection, $channel, $nick, $mode));
    }

    /**
     * Tests removeUser() with a channel that has mode data.
     */
    public function testRemoveUserWithModeData()
    {
        $event = $this->getMockUserModeEvent('+', '#channel1');
        $this->plugin->changeUserMode($event, $this->queue);

        $event = $this->getMockUserModeEvent('+', '#channel2');
        $this->plugin->changeUserMode($event, $this->queue);

        $channel = '#channel1,#channel2';
        $nick = 'nick';
        $mode = 'o';
        $params = array(
            'channels' => $channel,
        );
        $connection = $this->getMockConnection();
        $event = $this->getMockUserEvent();
        Phake::when($event)->getParams()->thenReturn($params);
        Phake::when($event)->getConnection()->thenReturn($connection);
        Phake::when($event)->getNick()->thenReturn($nick);

        $this->plugin->removeUser($event, $this->queue);

        $this->assertFalse($this->plugin->userHasMode($connection, '#channel1', $nick, $mode));
        $this->assertFalse($this->plugin->userHasMode($connection, '#channel2', $nick, $mode));
    }

    /**
     * Tests removeUser() with a channel that does not have mode data.
     */
    public function testRemoveUserWithoutModeData()
    {
        $channel = '#channel1,#channel2';
        $nick = 'nick';
        $mode = 'o';
        $params = array(
            'channels' => $channel,
        );
        $connection = $this->getMockConnection();
        $event = $this->getMockUserEvent();
        Phake::when($event)->getParams()->thenReturn($params);
        Phake::when($event)->getConnection()->thenReturn($connection);
        Phake::when($event)->getNick()->thenReturn($nick);

        $this->plugin->removeUser($event, $this->queue);

        $this->assertFalse($this->plugin->userHasMode($connection, '#channel1', $nick, $mode));
        $this->assertFalse($this->plugin->userHasMode($connection, '#channel2', $nick, $mode));
    }

    /**
     * Tests changeUserNick().
     */
    public function testChangeUserNick()
    {
        $otherChannel = '#channel1';
        $otherNick = 'otherNick';
        $event = $this->getMockUserModeEvent('+', $otherChannel, $otherNick);
        $connection = $event->getConnection();
        $this->plugin->changeUserMode($event, $this->queue);

        $oldNick = 'old';
        $newNick = 'new';
        $channel = '#channel2';
        $mode = 'o';
        $event = $this->getMockUserModeEvent('+', $channel, $oldNick);
        $this->plugin->changeUserMode($event, $this->queue);

        $event = $this->getMockUserEvent();
        $params = array('nickname' => $newNick);
        Phake::when($event)->getParams()->thenReturn($params);
        Phake::when($event)->getConnection()->thenReturn($connection);
        Phake::when($event)->getNick()->thenReturn($oldNick);
        $this->plugin->changeUserNick($event, $this->queue);

        $this->assertFalse($this->plugin->userHasMode($connection, $channel, $oldNick, $mode));
        $this->assertFalse($this->plugin->userHasMode($connection, $otherChannel, $oldNick, $mode));

        $this->assertTrue($this->plugin->userHasMode($connection, $channel, $newNick, $mode));
        $this->assertFalse($this->plugin->userHasMode($connection, $otherChannel, $newNick, $mode));

        $this->assertFalse($this->plugin->userHasMode($connection, $channel, $otherNick, $mode));
        $this->assertTrue($this->plugin->userHasMode($connection, $otherChannel, $otherNick, $mode));
    }

    /**
     * Data provider for testLoadUserModesWithSupportedPrefixes().
     *
     * @return array
     */
    public function dataProviderLoadUserModesWithSupportedPrefixes()
    {
        return array(
            array('='),
            array('*'),
            array('@'),
        );
    }

    /**
     * Tests loadUserModes() with supported prefixes.
     *
     * @param string $channelPrefix Channel name prefix
     * @dataProvider dataProviderLoadUserModesWithSupportedPrefixes
     */
    public function testLoadUserModesWithSupportedPrefixes($channelPrefix)
    {
        $channel = '#channel';
        $params = array(
            $channelPrefix . $channel,
            '~owner',
            '&admin',
            '@op',
            '%halfop',
            '+voice',
            '&@multi',
            'regular',
        );
        $event = $this->getMockEvent();
        $connection = $this->getMockConnection();
        Phake::when($event)->getParams()->thenReturn($params);
        Phake::when($event)->getConnection()->thenReturn($connection);

        $this->plugin->loadUserModes($event, $this->queue);

        $this->assertSame(array('q'), $this->plugin->getUserModes($connection, $channel, 'owner'));
        $this->assertSame(array('a'), $this->plugin->getUserModes($connection, $channel, 'admin'));
        $this->assertSame(array('o'), $this->plugin->getUserModes($connection, $channel, 'op'));
        $this->assertSame(array('h'), $this->plugin->getUserModes($connection, $channel, 'halfop'));
        $this->assertSame(array('v'), $this->plugin->getUserModes($connection, $channel, 'voice'));
        $this->assertSame(array('a', 'o'), $this->plugin->getUserModes($connection, $channel, 'multi'));
        $this->assertEmpty($this->plugin->getUserModes($connection, $channel, 'regular'));
    }

    /**
     * Tests loadUserModes() with an unsupported prefix.
     */
    public function testLoadUserModesWithUnsupportedPrefix()
    {
        $channel = '#channel';
        $params = array(
            '=' . $channel,
            '$unsupported',
        );
        $event = $this->getMockEvent();
        $connection = $this->getMockConnection();
        Phake::when($event)->getParams()->thenReturn($params);
        Phake::when($event)->getConnection()->thenReturn($connection);

        $this->plugin->loadUserModes($event, $this->queue);

        $this->assertEmpty($this->plugin->getUserModes($connection, $channel, 'unsupported'));
    }

    /**
     * Tests loadUserModes() with an overridden prefix.
     */
    public function testLoadUserModesWithOverriddenPrefix()
    {
        $channel = '#channel';
        $params = array(
            '=' . $channel,
            '$unsupported',
        );
        $event = $this->getMockEvent();
        $connection = $this->getMockConnection();
        Phake::when($event)->getParams()->thenReturn($params);
        Phake::when($event)->getConnection()->thenReturn($connection);

        $plugin = new Plugin(array('prefixes' => array('$' => 'd')));
        $plugin->setLogger($this->logger);
        $plugin->loadUserModes($event, $this->queue);

        $this->assertSame(array('d'), $plugin->getUserModes($connection, $channel, 'unsupported'));
    }

    /**
     * Tests that getSubscribedEvents() returns an array.
     */
    public function testGetSubscribedEvents()
    {
        $this->assertInternalType('array', $this->plugin->getSubscribedEvents());
    }

    /**
     * Returns a mock event.
     *
     * @return \Phergie\Irc\Event\EventInterface
     */
    protected function getMockEvent()
    {
        return Phake::mock('\Phergie\Irc\Event\EventInterface');
    }

    /**
     * Returns a mock user event.
     *
     * @return \Phergie\Irc\Event\UserEventInterface
     */
    protected function getMockUserEvent()
    {
        return Phake::mock('\Phergie\Irc\Event\UserEventInterface');
    }

    /**
     * Returns a mock connection.
     *
     * @return \Phergie\Irc\ConnectionInterface
     */
    protected function getMockConnection()
    {
        $connection = Phake::mock('\Phergie\Irc\ConnectionInterface');
        Phake::when($connection)->getNickname()->thenReturn('cnickname');
        Phake::when($connection)->getUsername()->thenReturn('cusername');
        Phake::when($connection)->getServerHostname()->thenReturn('chostname');
        return $connection;
    }
}
