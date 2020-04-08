<?php

/*
 * RakLib network library
 *
 *
 * This project is not affiliated with Jenkins Software LLC nor RakNet.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

declare(strict_types=1);

namespace raklib\server\ipc;

use pocketmine\utils\Binary;
use raklib\protocol\ACK;
use raklib\protocol\EncapsulatedPacket;
use raklib\protocol\PacketReliability;
use raklib\server\ipc\UserToRakLibThreadMessageProtocol as ITCProtocol;
use raklib\server\ServerEventSource;
use raklib\server\ServerInterface;
use function ord;
use function substr;

final class UserToRakLibThreadMessageReceiver implements ServerEventSource{
	/** @var InterThreadChannelReader */
	private $channel;

	public function __construct(InterThreadChannelReader $channel){
		$this->channel = $channel;
	}

	public function process(ServerInterface $server) : bool{
		if(($packet = $this->channel->read()) !== null){
			$id = ord($packet[0]);
			$offset = 1;
			if($id === ITCProtocol::PACKET_ENCAPSULATED){
				$identifier = Binary::readInt(substr($packet, $offset, 4));
				$offset += 4;
				$flags = ord($packet[$offset++]);
				$immediate = ($flags & ITCProtocol::ENCAPSULATED_FLAG_IMMEDIATE) !== 0;
				$needACK = ($flags & ITCProtocol::ENCAPSULATED_FLAG_NEED_ACK) !== 0;

				$encapsulated = new EncapsulatedPacket();
				$encapsulated->reliability = ord($packet[$offset++]);

				if($needACK){
					$encapsulated->identifierACK = Binary::readInt(substr($packet, $offset, 4));
					$offset += 4;
				}

				if(PacketReliability::isSequencedOrOrdered($encapsulated->reliability)){
					$encapsulated->orderChannel = ord($packet[$offset++]);
				}

				$encapsulated->buffer = substr($packet, $offset);
				$server->sendEncapsulated($identifier, $encapsulated, $immediate);
			}elseif($id === ITCProtocol::PACKET_RAW){
				$len = ord($packet[$offset++]);
				$address = substr($packet, $offset, $len);
				$offset += $len;
				$port = Binary::readShort(substr($packet, $offset, 2));
				$offset += 2;
				$payload = substr($packet, $offset);
				$server->sendRaw($address, $port, $payload);
			}elseif($id === ITCProtocol::PACKET_CLOSE_SESSION){
				$identifier = Binary::readInt(substr($packet, $offset, 4));
				$server->closeSession($identifier);
			}elseif($id === ITCProtocol::PACKET_SET_OPTION){
				$len = ord($packet[$offset++]);
				$name = substr($packet, $offset, $len);
				$offset += $len;
				$value = substr($packet, $offset);
				$server->setOption($name, $value);
			}elseif($id === ITCProtocol::PACKET_BLOCK_ADDRESS){
				$len = ord($packet[$offset++]);
				$address = substr($packet, $offset, $len);
				$offset += $len;
				$timeout = Binary::readInt(substr($packet, $offset, 4));
				$server->blockAddress($address, $timeout);
			}elseif($id === ITCProtocol::PACKET_UNBLOCK_ADDRESS){
				$len = ord($packet[$offset++]);
				$address = substr($packet, $offset, $len);
				$server->unblockAddress($address);
			}elseif($id === ITCProtocol::PACKET_RAW_FILTER){
				$pattern = substr($packet, $offset);
				$server->addRawPacketFilter($pattern);
			}elseif($id === ITCProtocol::PACKET_SHUTDOWN){
				$server->shutdown();
			}

			return true;
		}

		return false;
	}
}
