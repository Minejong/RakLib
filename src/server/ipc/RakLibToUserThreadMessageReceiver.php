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
use raklib\server\ipc\RakLibToUserThreadMessageProtocol as ITCProtocol;
use raklib\server\ServerEventListener;
use function inet_ntop;
use function ord;
use function substr;

final class RakLibToUserThreadMessageReceiver{
	/** @var InterThreadChannelReader */
	private $channel;

	public function __construct(InterThreadChannelReader $channel){
		$this->channel = $channel;
	}

	public function handle(ServerEventListener $listener) : bool{
		if(($packet = $this->channel->read()) !== null){
			$id = ord($packet[0]);
			$offset = 1;
			if($id === ITCProtocol::PACKET_ENCAPSULATED){
				$identifier = Binary::readInt(substr($packet, $offset, 4));
				$offset += 4;
				$buffer = substr($packet, $offset);
				$listener->handleEncapsulated($identifier, $buffer);
			}elseif($id === ITCProtocol::PACKET_RAW){
				$len = ord($packet[$offset++]);
				$address = substr($packet, $offset, $len);
				$offset += $len;
				$port = Binary::readShort(substr($packet, $offset, 2));
				$offset += 2;
				$payload = substr($packet, $offset);
				$listener->handleRaw($address, $port, $payload);
			}elseif($id === ITCProtocol::PACKET_SET_OPTION){
				$len = ord($packet[$offset++]);
				$name = substr($packet, $offset, $len);
				$offset += $len;
				$value = substr($packet, $offset);
				$listener->handleOption($name, $value);
			}elseif($id === ITCProtocol::PACKET_OPEN_SESSION){
				$identifier = Binary::readInt(substr($packet, $offset, 4));
				$offset += 4;
				$len = ord($packet[$offset++]);
				$rawAddr = substr($packet, $offset, $len);
				$offset += $len;
				$address = inet_ntop($rawAddr);
				if($address === false){
					throw new \RuntimeException("Unexpected invalid IP address in inter-thread message");
				}
				$port = Binary::readShort(substr($packet, $offset, 2));
				$offset += 2;
				$clientID = Binary::readLong(substr($packet, $offset, 8));
				$listener->openSession($identifier, $address, $port, $clientID);
			}elseif($id === ITCProtocol::PACKET_CLOSE_SESSION){
				$identifier = Binary::readInt(substr($packet, $offset, 4));
				$offset += 4;
				$len = ord($packet[$offset++]);
				$reason = substr($packet, $offset, $len);
				$listener->closeSession($identifier, $reason);
			}elseif($id === ITCProtocol::PACKET_ACK_NOTIFICATION){
				$identifier = Binary::readInt(substr($packet, $offset, 4));
				$offset += 4;
				$identifierACK = Binary::readInt(substr($packet, $offset, 4));
				$listener->notifyACK($identifier, $identifierACK);
			}elseif($id === ITCProtocol::PACKET_REPORT_PING){
				$identifier = Binary::readInt(substr($packet, $offset, 4));
				$offset += 4;
				$pingMS = Binary::readInt(substr($packet, $offset, 4));
				$listener->updatePing($identifier, $pingMS);
			}

			return true;
		}

		return false;
	}
}
