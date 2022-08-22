<?php

/* 
 * Minecraft World's regions cleaner - CLI
 * https://github.com/cxxi/WorldCleaner
 * Licence MIT 2021
 * by cxxi
 */

class WorldCleaner
{
	private const WELCOME_MSG = "*\n* World's regions cleaner - CLI\n* by cxxi\n*\n\n";
	private const ASK_STEP_1 = "Type the path of regions directory (eg: ./ ) :\n";
	private const ERR_STEP_1 = "\033[31mInvalid or unknow directory !\033[0m\n";
	private const ASK_STEP_2 = "Type coordinate (x/z in blocks) of cornerTopLeft for keeped area (eg. 500/500):\n";
	private const ERR_STEP_2 = "\033[31mInvalid coordinate for cornerTopLeft of keeped area !\033[0m\n";
	private const ASK_STEP_3 = "Type coordinate (x/z in blocks) of cornerBottomRight for keeped area (eg. -500/-500):\n";
	private const ERR_STEP_3 = "\033[31mInvalid coordinate for cornerBottomRight of keeped area !\033[0m\n";
	private const ASK_STEP_4 = "Confirm regions will be keeped : (yes/no)\n";
	private const ERR_STEP_4 = "\033[31mKeeped area is empty ! \033[0m\n";
	private const ASK_STEP_5 = "Confirm regions removal : (yes/no)\n";
	private const ERR_STEP_5 = "\033[31mKeeped area is not included in full area of world !\033[0m\n";

	public function __construct()
	{
		$this->separator = '/';
		$this->countRegions = 0;
		$this->step = 1;

		$this->pathRegionsDir = null;

		if ($this->checkDirValidity('./')) {
			$this->pathRegionsDir = './';
			$this->step = 2;
		}
		
		$this->keepedArea = [
			"cornerTopLeft" => null,
			"cornerBottomRight" => null,
			"range" => null
		];

		$this->toDelete = [];
	}

	public function execute(): string
	{
		echo self::WELCOME_MSG;

		while($this->step <= 6)
		{
			$successStep = false;

			switch($this->step)
			{
				case 1:

					echo self::ASK_STEP_1;
					$handle = fopen ("php://stdin","r");
					$line = trim(fgets($handle));
					if (is_dir(trim($line)) && $this->checkDirValidity($line)) {
						$this->pathRegionsDir = trim($line);
						echo __class__." have found ".$this->countRegions." regions.\n\n";
						$successStep = true;
					} else {
						echo self::ERR_STEP_1;
					}

					break;

				case 2:

					echo self::ASK_STEP_2;
					$handle = fopen ("php://stdin","r");
					$line = trim(fgets($handle));
					if (!preg_match('/^-?[0-9]+\/-?[0-9]+$/', $line)) break;
					[$x, $y] = explode($this->separator, $line);
					if (is_int(intval($x)) && is_int(intval($y))) {
						$this->keepedArea['cornerTopLeft'] = self::convertCoorToRegionCoor($x, $y);
						echo "CornerTopLeft will be r.".$this->keepedArea['cornerTopLeft'][0].".".$this->keepedArea['cornerTopLeft'][1].".mca\n\n";
						$successStep = true;
					} else {
						echo self::ERR_STEP_2;
					}

					break;

				case 3:

					echo self::ASK_STEP_3;
					$handle = fopen ("php://stdin","r");
					$line = trim(fgets($handle));
					if (!preg_match('/^-?[0-9]+\/-?[0-9]+$/', $line)) break;
					[$x, $y] = explode($this->separator, $line);
					if (is_int(intval($x)) && is_int(intval($y))) {
						$this->keepedArea['cornerBottomRight'] = self::convertCoorToRegionCoor($x, $y);
						echo "CornerBottomRight will be r.".$this->keepedArea['cornerBottomRight'][0].".".$this->keepedArea['cornerBottomRight'][1].".mca\n\n";
						$successStep = true;
					} else {
						echo self::ERR_STEP_3;
					}

					break;

				case 4:

					$this->keepedArea['range'] = $this->getKeepedArea();
					if (count($this->keepedArea['range']) < 1) {
						echo self::ERR_STEP_4;
						$this->step = 2;
						break;
					}

					echo "KeepedArea :\n".$this->coorToString($this->keepedArea['range'])."\n\n";
					echo self::ASK_STEP_4;
					$handle = fopen ("php://stdin","r");
					trim(fgets($handle)) == 'yes'
						? $successStep = true
						: $this->step = 2
					;

					echo "\n";
					break;

				case 5:

					$regions = $this->getRegionsList();
					if (!$this->checkAreaInclusivity($regions)) {
						echo self::ERR_STEP_5;
						$this->step = 2;
						break;
					}

					$keeped  = $this->coorToString($this->keepedArea['range'], false);
					$regions = $this->coorToString($regions, false);
					$result  = [];
					$countKeep = 0;
					$countDel = 0;

					foreach($regions as $region)
					{
						if (in_array($region, $keeped)) {
							$result[] = $region;
							$countKeep++;
						} else {
							$result[] = "\033[31m".$region."\033[0m";
							$this->toDelete[] = $region;
							$countDel++;
						}
					}

					echo "Summary of the action to be performed : (keeped: ".$countKeep.", \033[31mdeleted: ".$countDel."\033[0m\n)\n".implode(',', $result)."\n\n";
					echo self::ASK_STEP_5;
					$handle = fopen ("php://stdin","r");
					trim(fgets($handle)) == 'yes'
						? $successStep = true
						: $this->step = 2
					;

					echo "\n";
					break;

				case 6:

					echo "Deleting..\n";
					$files = array_map(function($r){
						return "r.".str_replace(',', '.', substr($r, 1, strlen($r)-2)).".mca";
					}, $this->toDelete);
					$countDel = 0;

					foreach ($files as $file)
					{
						echo "\033[31m- ".$file."\033[0m\n";
						unlink($this->pathRegionsDir.$file);
						$countDel++;
					}

					echo "Deleted ".$countDel." files Successfully\n";
					$successStep = true;
					break;
			}

			if ($successStep) $this->step++;
		}

		echo "\nEnd\n";
		return 1;
	}

	private static function convertCoorToRegionCoor(int $x, int $y)
	{
		$x = $x/512 >= 0 ? ceil($x/512) : floor($x/512);
		$y = $y/512 >= 0 ? ceil($y/512) : floor($y/512);
		return [$x == -0 ? 0 : $x, $y == -0 ? 0 : $y];
	}

	private function checkDirValidity($path): bool
	{
		$dirContent = scandir($path);
		foreach ($dirContent as $filename)
		{
			if ($filename == '.' || $filename == '..' || $filename == 'WorldCleaner.php') continue;
			if (pathinfo($path.$filename, PATHINFO_EXTENSION) != 'mca' || strpos($filename, 'r.') != 0) {
				return false;
			}
		}

		$this->countRegions = count($dirContent);
		return true;
	}

	private function checkAreaInclusivity($regionArr): bool
	{
		$unknowRegion = array_diff(
			$this->coorToString($this->keepedArea['range'], false), 
			$this->coorToString($regionArr, false)
		);

		return count($unknowRegion) == 0;
	}

	private function getRegionsList(): array
	{
		$dirContent = array_filter(scandir($this->pathRegionsDir), function($filename){
			return $filename != '.' && $filename != '..' && $filename != 'WorldCleaner.php';
		});

		$regions = [];

		foreach($dirContent as $region)
		{
			preg_match('/^r\.(-?[0-9]+\.-?[0-9]+)\.mca$/', $region, $matches);
			if (!empty($matches)) $regions[] = explode('.', $matches[1]);
		}

		return $regions;
	}

	private function getKeepedArea(): array
	{
		[$x1, $y1] = $this->keepedArea['cornerTopLeft'];
		[$x2, $y2] = $this->keepedArea['cornerBottomRight'];
		$toKeep = [];

		foreach (range($x1, $x2) as $x) {
			foreach (range($y1, $y2) as $y) {
				$toKeep[] = [intval($x), intval($y)];
			}	
		}

		return $toKeep;
	}

	private function coorToString($array, $strReturn = true)
	{
		$result = array_map(function($item){
			return "[".$item[0].",".$item[1]."]";
		}, $array);

		return $strReturn ? implode(',', $result) : $result; 
	}
}

(new WorldCleaner())->execute();