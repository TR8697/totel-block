<?php

namespace TR8697;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use SQLite3;

class Main extends PluginBase implements Listener {

	private $database;

	public function onEnable(): void {
		$this->database = new SQLite3($this->getDataFolder() . "blocks.db");
		$this->database->exec("CREATE TABLE IF NOT EXISTS blocks (id INTEGER PRIMARY KEY AUTOINCREMENT, player TEXT, block TEXT, count INTEGER DEFAULT 0, UNIQUE(player, block) ON CONFLICT REPLACE)");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onDisable(): void {
		if ($this->database instanceof SQLite3) {
			$this->database->close();
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
		if ($command->getName() === "kblock") {
			$playerName = $sender->getName();

			$blocks = $this->getBlocksByPlayer($playerName);
			$blockCount = count($blocks);

			if ($blockCount > 0) {
				$totalCount = $this->getBlockCountByPlayer($playerName);
				$message = "§aToplam Kırdığınız Blok Sayısı: §e$totalCount";

				$sender->sendMessage($message);
			} else {
				$sender->sendMessage("Kırdığınız blok bulunmamaktadır.");
			}

			return true;
		}

		return false;
	}

	/**
	 * @priority HIGHEST
	 * @ignoreCancelled true
	 */
	public function onBlockBreak(BlockBreakEvent $event): void {
		$player = $event->getPlayer();
		$block = $event->getBlock();

		$this->saveBlock($player->getName(), $block->getName());
	}

	private function saveBlock(string $playerName, string $blockName): void {
		$stmt = $this->database->prepare("INSERT INTO blocks (player, block, count) VALUES (:player, :block, 1) ON CONFLICT(player, block) DO UPDATE SET count = count + 1");
		$stmt->bindValue(":player", $playerName, SQLITE3_TEXT);
		$stmt->bindValue(":block", $blockName, SQLITE3_TEXT);
		$stmt->execute();
	}

	private function getBlocksByPlayer(string $playerName): array {
		$stmt = $this->database->prepare("SELECT block FROM blocks WHERE player = :player");
		$stmt->bindValue(":player", $playerName, SQLITE3_TEXT);
		$result = $stmt->execute();

		$blocks = [];
		while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
			$blocks[] = $row["block"];
		}

		return $blocks;
	}

	private function getBlockCountByPlayer(string $playerName): int {
		$stmt = $this->database->prepare("SELECT SUM(count) AS total FROM blocks WHERE player = :player");
		$stmt->bindValue(":player", $playerName, SQLITE3_TEXT);
		$result = $stmt->execute();

		$row = $result->fetchArray(SQLITE3_ASSOC);
		return (int) $row["total"] ?? 0;
	}
}
