<?php

# Ignore inline messages (via @)
if ($v->via_bot) die;

# Define variables
if (!defined('WhatsAppSave_Vars')) {
	define('WhatsAppSave_Vars', true);
	
	# Create a share URL format
	function WhatsAppURL ($text, $number = null) {
		$args['text'] = $text;
		if (!is_numeric($number)) $number = '';
		return 'https://wa.me/' . $number . '?' . http_build_query($args);
	}
}

# Commands for privat chat
if ($v->chat_type == 'private') {
	if ($bot->configs['database']['status'] && $user['status'] !== 'started') $db->setStatus($v->user_id, 'started');
	$watermark = PHP_EOL . '@NeleBots';
	
	# Delete message from the list
	if (strpos($v->query_data, 'deleteMessage-') === 0) {
		if (isset($user['settings']['messages'][explode('-', $v->query_data, 2)[1]])) unset($user['settings']['messages'][explode('-', $v->query_data, 2)[1]]);
		$user['settings']['messages']= array_values($user['settings']['messages']);
		$db->query('UPDATE users SET settings = ? WHERE id = ?', [json_encode($user['settings']), $v->user_id]);
		$v->query_data = 'list';
	}
	# Remove default number
	elseif ($v->query_data == 'removeNumber') {
		if (isset($user['settings']['number'])) unset($user['settings']['number']);
		$db->query('UPDATE users SET settings = ? WHERE id = ?', [json_encode($user['settings']), $v->user_id]);
		$v->query_data = 'list';
	}
	# Start message
	if ($v->command == 'start' || $v->command == 'start inline' || $v->query_data == 'start') {
		$t = $bot->bold('🔖 WhatsApp Save Bot') . PHP_EOL
		. $bot->italic($tr->getTranslation('startMessage')) . $watermark;
		$buttons[][] = $bot->createInlineButton($tr->getTranslation('listButton'), 'list');
		$buttons[] = [
			$bot->createInlineButton($tr->getTranslation('aboutButton'), 'about'),
			$bot->createInlineButton($tr->getTranslation('helpButton'), 'help')
		];
		$buttons[][] = $bot->createInlineButton($tr->getTranslation('changeLanguage'), 'changeLanguage');
		if ($v->reply_to_message) {
			$bot->sendMessage($v->chat_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
			$bot->deleteMessage($v->chat_id, $v->message_id);
		} elseif ($v->command) {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		} else {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		}
		die;
	}
	# List command
	elseif (strpos($v->query_data, 'list') === 0) {
		$t = $tr->getTranslation('listMessage');
		$page = explode('-', $v->query_data, 2)[1];
		if (isset($user['settings']['messages']) && !empty($user['settings']['messages'])) {
			$user['settings']['messages']= array_values($user['settings']['messages']);
			$t = $bot->bold($tr->getTranslation('listOfMessages')) . PHP_EOL;
			$limit = 8;
			$offset = $limit * $page;
			$mcount = 0;
			$formenu = 4;
			foreach (range($offset, $offset + $limit - 1) as $id) {
				if (isset($user['settings']['messages'][$id])) {
					$d += 1;
					$text = $bot->specialchars($user['settings']['messages'][$id]);
					$text = str_replace(PHP_EOL, '	', $text);
					if (strlen($text) > 32) $text = substr($text, 0, 32) . '...';
					$t .= PHP_EOL . ($d) . '️⃣: ' . $text;
					if (isset($buttons[$mcount]) && count($buttons[$mcount]) >= $formenu) $mcount += 1;
					$buttons[$mcount][] = $bot->createInlineButton(($d) . '️⃣', 'message-' . $id);
					$buttons[$mcount][] = $bot->createInlineButton('🗑', 'deleteMessage-' . $id);
				}
			}
			if (isset($user['settings']['messages'][$offset + $limit])) $buttons[][] = $bot->createInlineButton('➡️', 'list-' . ($page + 1));
			if ($page) $buttons[][] = $bot->createInlineButton('⬅️', 'list-' . ($page - 1));
		} else {
			$t = $tr->getTranslation('listEmpty');
		}
		if (isset($user['settings']['number'])) $buttons[][] = $bot->createInlineButton($tr->getTranslation('removeNumber'), 'removeNumber');
		$buttons[][] = $bot->createInlineButton('◀️', 'start');
		if ($v->command) {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		} else {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		}
	}
	# Show saved message
	elseif (strpos($v->query_data, 'message-') === 0 && isset($user['settings']['messages'][explode('-', $v->query_data, 2)[1]])) {
		$text = $user['settings']['messages'][explode('-', $v->query_data, 2)[1]];
		$url = WhatsAppURL($text);
		$t = $tr->getTranslation('messageInfo', [$bot->specialchars($text), $bot->specialchars($url)]);
		$buttons[][] = $bot->createInlineButton($tr->getTranslation('sendMessageButton'), $url, 'url');
		if ($user['settings']['number']) {
			$url2 = WhatsAppURL($text, $user['settings']['number']);
			$buttons[][] = $bot->createInlineButton($tr->getTranslation('sendToContactButton', [$user['settings']['number']]), $url2, 'url');
		}
		$buttons[][] = $bot->createInlineButton('◀️', 'list');
		if ($v->command) {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		} else {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		}
	}
	# Help command
	elseif (in_array('help', [$v->command, $v->query_data])) {
		$t = $tr->getTranslation('helpMessage');
		$buttons[][] = $bot->createInlineButton('◀️', 'start');
		if ($v->command) {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		} else {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		}
	}
	# About command
	elseif (in_array('about', [$v->command, $v->query_data])) {
		$t = $tr->getTranslation('aboutMessage');
		$buttons[][] = $bot->createInlineButton('☕️ Buy me a coffee', 'https://www.paypal.com/donate/?hosted_button_id=3NJZ7EQDFSG7J', 'url');
		$buttons[][] = $bot->createInlineButton('◀️', 'start');
		if ($v->command) {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		} else {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		}
	}
	# Change language
	elseif (strpos($v->query_data, 'changeLanguage') === 0) {
		$langnames = [
			'ar' => '🇦🇪 عربى',
			'en' => '🇬🇧 English',
			'fr' => '🇫🇷 Français',
			'it' => '🇮🇹 Italiano',
			'pt-BR' => '🇧🇷 Português'
		];
		if (strpos($v->query_data, 'changeLanguage-') === 0) {
			$select = str_replace('changeLanguage-', '', $v->query_data);
			if (in_array($select, array_keys($langnames))) {
				$tr->setLanguage($select);
				$user['lang'] = $select;
				$db->query('UPDATE users SET lang = ? WHERE id = ?', [$user['lang'], $user['id']]);
			}
		}
		$langnames[$user['lang']] .= ' ✅';
		$t = '🔡 Select your language';
		$formenu = 2;
		$mcount = 0;
		foreach ($langnames as $lang_code => $name) {
			if (isset($buttons[$mcount]) && count($buttons[$mcount]) >= $formenu) $mcount += 1;
			$buttons[$mcount][] = $bot->createInlineButton($name, 'changeLanguage-' . $lang_code);
		}
		$buttons[][] = $bot->createInlineButton('◀️', 'start');
		$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
		$bot->answerCBQ($v->query_id);
	}
	# Save message
	elseif ($v->query_data == 'save' && $v->reply_to_message['text']) {
		if (strlen($v->reply_to_message['text']) > 512) {
			$bot->answerCBQ($v->query_id, '⚠️ Too many characters!');
			die;
		}
		$user['settings']['messages'][] = $v->reply_to_message['text'];
		$db->query('UPDATE users SET settings = ? WHERE id = ?', [json_encode($user['settings']), $v->user_id]);
		$t = $tr->getTranslation('savedMessage');
		$buttons[][] = $bot->createInlineButton('◀️', 'start');
		$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
		$bot->answerCBQ($v->query_id);
	}
	# Save number
	elseif ($v->query_data == 'numberSave' && $v->reply_to_message['contact']['phone_number']) {
		$user['settings']['number'] = $v->reply_to_message['contact']['phone_number'];
		$db->query('UPDATE users SET settings = ? WHERE id = ?', [json_encode($user['settings']), $v->user_id]);
		$t = $tr->getTranslation('savedNumber');
		$buttons[][] = $bot->createInlineButton('◀️', 'start');
		$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
		$bot->answerCBQ($v->query_id);
	}
	# Delete message
	elseif ($v->query_data == 'del' && $v->reply_to_message['message_id']) {
		$bot->answerCBQ($v->query_id);
		$bot->deleteMessage($v->chat_id, $v->message_id);
		$bot->deleteMessage($v->chat_id, $v->reply_to_message['message_id']);
	}
	# Create WhatsApp link
	else {
		if ($v->text && !$v->command && !$v->query_data) {
			$url = WhatsAppURL($v->text, $number);
			$buttons[][] = $bot->createInlineButton($tr->getTranslation('sendMessageButton'), $url, 'url');
			if ($user['settings']['number']) {
				$url2 = WhatsAppURL($text, $user['settings']['number']);
				$buttons[][] = $bot->createInlineButton($tr->getTranslation('sendToContactButton', [$user['settings']['number']]), $url2, 'url');
			}
			$buttons[][] = $bot->createInlineButton($tr->getTranslation('saveButton'), 'save');
			$t = $bot->bold($tr->getTranslation('linkCreated'), 1) . PHP_EOL . $bot->code($url, 1);
			$bot->sendMessage($v->chat_id, $t, $buttons, 'def', 0, $v->message_id);
		} elseif (isset($v->c_number)) {
			$buttons[][] = $bot->createInlineButton($tr->getTranslation('saveDefault'), 'numberSave');
			$buttons[][] = $bot->createInlineButton('❌', 'del');
			$t = $bot->bold($tr->getTranslation('saveNumber'), 1) . PHP_EOL . $bot->code($v->c_number, 1);
			$bot->sendMessage($v->chat_id, $t, $buttons, 'def', 0, $v->message_id);
		} elseif ($v->message_id) {
			$bot->deleteMessage($v->chat_id, $v->message_id);
		}
	}
}

?>
