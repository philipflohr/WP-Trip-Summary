<?php
/**
 * Copyright (c) 2014-2023 Alexandru Boia
 *
 * Redistribution and use in source and binary forms, with or without modification, 
 * are permitted provided that the following conditions are met:
 * 
 *	1. Redistributions of source code must retain the above copyright notice, 
 *		this list of conditions and the following disclaimer.
 *
 * 	2. Redistributions in binary form must reproduce the above copyright notice, 
 *		this list of conditions and the following disclaimer in the documentation 
 *		and/or other materials provided with the distribution.
 *
 *	3. Neither the name of the copyright holder nor the names of its contributors 
 *		may be used to endorse or promote products derived from this software without 
 *		specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" 
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, 
 * THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. 
 * IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY 
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES 
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; 
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) 
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, 
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) 
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED 
 * OF THE POSSIBILITY OF SUCH DAMAGE.
 */

if (!defined('ABP01_LOADED') || !ABP01_LOADED) {
	exit;
}

class Abp01_Installer {
	/**
	 * @var int Status code returned when all installation requirements have been met
	 */
	const ALL_REQUIREMENTS_MET = Abp01_Installer_RequirementStatusCode::ALL_REQUIREMENTS_MET;

	/**
	 * @var int Error code returned when an incompatible PHP version is detected upon installation
	 */
	const INCOMPATIBLE_PHP_VERSION = Abp01_Installer_RequirementStatusCode::INCOMPATIBLE_PHP_VERSION;

	/**
	 * @var int Error code returned when an incompatible WordPress version is detected upon installation
	 */
	const INCOMPATIBLE_WP_VERSION = Abp01_Installer_RequirementStatusCode::INCOMPATIBLE_WP_VERSION;

	/**
	 * @var int Error code returned when LIBXML is not found
	 */
	const SUPPORT_LIBXML_NOT_FOUND = Abp01_Installer_RequirementStatusCode::SUPPORT_LIBXML_NOT_FOUND;

	/**
	 * @var int Error code returned when MySQL Spatial extension is not found
	 */
	const SUPPORT_MYSQL_SPATIAL_NOT_FOUND = Abp01_Installer_RequirementStatusCode::SUPPORT_MYSQL_SPATIAL_NOT_FOUND;

	/**
	 * @var int Error code returned when MySqli extension is not found
	 */
	const SUPPORT_MYSQLI_NOT_FOUND = Abp01_Installer_RequirementStatusCode::SUPPORT_MYSQLI_NOT_FOUND;

	/**
	 * @var int Error code returned when the installation capabilities cannot be detected
	 */
	const COULD_NOT_DETECT_INSTALLATION_CAPABILITIES = Abp01_Installer_RequirementStatusCode::COULD_NOT_DETECT_INSTALLATION_CAPABILITIES;

	/**
	 * @var string WP options key for current plug-in version
	 */
	const OPT_VERSION = 'abp01.option.version';

	/**
	 * @var Abp01_Env The current instance of the plug-in environment
	 */
	private $_env;

	/**
	 * @var mixed The last occured error
	 */
	private $_lastError = null;

	/**
	 * @var bool Whether or not to install lookup data
	 */
	private $_installLookupData;
	
	/**
	 * @var array An array of cached lookup data items definitions
	 */
	private $_cachedDefinitions = null;

	/**
	 * Creates a new installer instance
	 * @param bool $installLookupData Whether or not to install lookup data
	 */
	public function __construct($installLookupData = true) {
		$this->_env = Abp01_Env::getInstance();
		$this->_installLookupData = $installLookupData;
	}

	private function _getVersion() {
		return $this->_env->getVersion();
	}

	private function _isUpdatedNeeded($version, $installedVersion) {
		return $version != $installedVersion;
	}

	private function _getInstalledVersion() {
		$version = null;
		if (function_exists('get_option')) {
			$version = get_option(self::OPT_VERSION, null);
		}
		return $version;
	}

	private function _update($version, $installedVersion) {
		$this->_reset();
		$result = true;

		if (empty($installedVersion)) {
			//If no installed version is set, this is the very first version, 
			//  we need to run all updates in order
			$result = $this->_updateTo02Beta() 
				&& $this->_updateTo021() 
				&& $this->_updateTo022()
				&& $this->_updateTo024();
		} else {
			//...otherwise, we need to see 
			//  which installed version this is
			switch ($installedVersion) {
				case '0.2b':
					$result = $this->_updateTo021() 
						&& $this->_updateTo022()
						&& $this->_updateTo024();
				break;
				case '0.2.1':
					$result = $this->_updateTo022() 
						&& $this->_updateTo024();
				break;
				case '0.2.2':
				case '0.2.3':
					$result = $this->_updateTo024();
					break;
			}
		}

		//Finally, run the update to 0.2.7, 
		//  if the pervious updates (if there were any), 
		//  were successful
		if ($result) {
			$result = $this->_updateTo027();
		}

		if ($result) {
			update_option(self::OPT_VERSION, $version);
		}
		return $result;
	}

	private function _updateTo02Beta() {
		try {
			if ($this->_createTable($this->_getRouteDetailsLookupTableDefinition())) {
				return $this->_syncExistingLookupAssociations();
			} else {
				return false;
			}
		} catch (Exception $exc) {
			$this->_lastError = $exc;
		}
		return false;
	}
	
	private function _syncExistingLookupAssociations() {
		$service = new Abp01_Installer_Service_SyncExistingLookupAssociations($this->_env);
		return $service->execute();
	}

	private function _updateTo021() {
		$step = new Abp01_Installer_Step_Update_UpdateTo021($this->_env);
		return $this->_executeStep($step);
	}

	private function _executeStep(Abp01_Installer_Step $step) {
		$result = $step->execute();
		$this->_lastError = $step->getLastError();
		return $result;
	}

	private function _ensureStorageDirectories() {
		$result = true;
		$rootStorageDir = $this->_env->getRootStorageDir();
		
		if (!is_dir($rootStorageDir)) {
			@mkdir($rootStorageDir);
		}

		if (is_dir($rootStorageDir)) {
			$tracksStorageDir = $this->_env->getTracksStorageDir();
			if (!is_dir($tracksStorageDir)) {
				@mkdir($tracksStorageDir);
			}

			if (is_dir($tracksStorageDir)) {
				$cacheStorageDir = $this->_env->getCacheStorageDir();
				if (!is_dir($cacheStorageDir)) {
					@mkdir($cacheStorageDir);
				}

				$result = is_dir($cacheStorageDir);
			} else {
				$result = false;
			}
		} else {
			$result = false;
		}

		return $result;
	}

	private function _removeStorageDirectories() {
		$rootStorageDir = $this->_env->getRootStorageDir();
		$tracksStorageDir = $this->_env->getTracksStorageDir();
		$cacheStorageDir = $this->_env->getCacheStorageDir();

		if ($this->_removeDirectoryAndContents($tracksStorageDir) 
			&& $this->_removeDirectoryAndContents($cacheStorageDir)) {
			return $this->_removeDirectoryAndContents($rootStorageDir);
		} else {
			return false;
		}
	}

	private function _removeDirectoryAndContents($directoryPath) {
		if (!is_dir($directoryPath)) {
			return true;
		}

		$failedCount = 0;
		$entries = @scandir($directoryPath, SCANDIR_SORT_ASCENDING);

		//Remove the files
		if (is_array($entries)) {
			foreach ($entries as $entry) {
				if ($entry != '.' && $entry != '..') {
					$toRemoveFilePath = wp_normalize_path(sprintf('%s/%s', 
						$directoryPath, 
						$entry));

					if (!@unlink($toRemoveFilePath)) {
						$failedCount++;
					}
				}
			}
		}

		//And if no file removal failed,
		//  remove the directory
		if ($failedCount == 0) {
			return @rmdir($directoryPath);
		} else {
			return false;
		}
	}

	private function _updateTo022() {
		$step = new Abp01_Installer_Step_Update_UpdateTo022($this->_env);
		return $this->_executeStep($step);
	}

	private function _updateTo024() {
		$step = new Abp01_Installer_Step_Update_UpdateTo024($this->_env);
		return $this->_executeStep($step);
	}

	private function _updateTo027() {
		$step = new Abp01_Installer_Step_Update_UpdateTo027($this->_env);
		return $this->_executeStep($step);
	}

	private function _installStorageDirsSecurityAssets() {
		$rootStorageDir = $this->_env->getRootStorageDir();
		$tracksStorageDir = $this->_env->getTracksStorageDir();
		$cacheStorageDir = $this->_env->getCacheStorageDir();

		$rootAssets = array(
			array(
				'name' => 'index.php',
				'contents' => $this->_getGuardIndexPhpFileContents(3),
				'type' => 'file'
			)
		);

		$tracksAssets = array(
			array(
				'name' => 'index.php',
				'contents' => $this->_getGuardIndexPhpFileContents(4),
				'type' => 'file'
			),
			array(
				'name' => '.htaccess',
				'contents' => $this->_getTrackAssetsGuardHtaccessFileContents(),
				'type' => 'file'
			)
		);

		$this->_installAssetsForDirectory($rootStorageDir, 
			$rootAssets);
		$this->_installAssetsForDirectory($tracksStorageDir, 
			$tracksAssets);
		$this->_installAssetsForDirectory($cacheStorageDir, 
			$tracksAssets);

		return true;
	}

	private function _installAssetsForDirectory($targetDir, $assetsDesc) {
		if (!is_dir($targetDir)) {
			return false;
		}

		foreach ($assetsDesc as $asset) {
			$result = false;
			$assetPath = wp_normalize_path(sprintf('%s/%s', 
				$targetDir, 
				$asset['name']));

			if ($asset['type'] == 'file') {
				$assetHandle = @fopen($assetPath, 'w+');
				if ($assetHandle) {
					fwrite($assetHandle, $asset['contents']);
					fclose($assetHandle);
					$result = true;
				}
			} else if ($asset['type'] == 'directory') {
				if (!is_dir($assetPath)) {
					@mkdir($assetPath);
				}
				$result = is_dir($assetPath);
			}

			if (!$result) {
				return false;
			}
		}
		return true;
	}

	private function _getTrackAssetsGuardHtaccessFileContents() {
		return join("\n", array(
			'<FilesMatch "\.cache">',
				"\t" . 'order allow,deny',
				"\t" . 'deny from all',
			'</FilesMatch>',
			'<FilesMatch "\.gpx">',
				"\t" . 'order allow,deny',
				"\t" . 'deny from all',
			'</FilesMatch>',
			'<FilesMatch "\.geojson">',
				"\t" . 'order allow,deny',
				"\t" . 'deny from all',
			'</FilesMatch>'
		));
	}

	private function _getGuardIndexPhpFileContents($redirectCount) {
		return '<?php header("Location: ' . str_repeat('../', $redirectCount) . 'index.php"); exit;';
	}

	/**
	 * Checks the current plug-in package version, the currently installed version
	 *  and runs the update operation if they differ.
	 * 
	 * @return bool The operation result: true if succeeded, false otherwise
	 */
	public function updateIfNeeded() {
		$result = true;
		$version = $this->_getVersion();
		$installedVersion = $this->_getInstalledVersion();

		if ($this->_isUpdatedNeeded($version, $installedVersion)) {
			$result = $this->_update($version, $installedVersion);
		}

		return $result;
	}

	/**
	 * Checks whether the plug-in can be installed and returns 
	 *  a code that describes the reason it cannot be installed
	 *  or Installer::INSTALL_OK if it can.
	 * 
	 * @return int The error code that describes the result of the test.
	 */
	public function checkRequirements() {
		$this->_reset();

		try {
			$checker = $this->_getChecker();
			$result = $checker->check();
			if ($result !== self::ALL_REQUIREMENTS_MET) {
				$this->_lastError = $checker->getLastError();
			}

			return $result;
		} catch (Exception $e) {
			$this->_lastError = $e;
			return self::COULD_NOT_DETECT_INSTALLATION_CAPABILITIES;
		}
	}

	private function _getChecker() {
		return new Abp01_Installer_Requirement_Checker(
			new Abp01_Installer_Requirement_Provider_Default(
				$this->_env
			)
		);
	}

	/**
	 * Activates the plug-in. 
	 * If a step of the activation process fails, 
	 *  the plug-in attempts to rollback the steps that did successfully execute.
	 * The activation process is idempotent, that is, 
	 *  it will not perform the same operations twice.
	 * 
	 * @return bool True if the operation succeeded, false otherwise.
	 */
	public function activate() {
		$this->_reset();
		try {
			if (!$this->_installStorageDirectoryAndAssets()) {
				//Ensure no partial directory and file structure remains
				$this->_removeStorageDirectories();
				return false;
			}

			if (!$this->_installSchema()) {
				//Ensure no partial directory and file structure remains
				$this->_removeStorageDirectories();
				return false;
			}

			if (!$this->_installData()) {
				//Ensure no partial directory and file structure remains
				$this->_removeStorageDirectories();
				//Remove schema as well
				$this->_uninstallSchema();
				return false;
			} else {
				if ($this->_createCapabilities()) {
					update_option(self::OPT_VERSION, $this->_getVersion());
					return true;
				} else {
					return false;
				}
			}
		} catch (Exception $e) {
			$this->_lastError = $e;
		}
		return false;
	}

	/**
	 * Deactivates the plug-in.
	 * If a step of the activation process fails, 
	 *  the plug-in attempts to rollback the steps 
	 *  that did successfully execute.
	 * 
	 * @return bool True if the operation succeeded, false otherwise. 
	 */
	public function deactivate() {
		$this->_reset();
		try {
			return $this->_removeCapabilities();
		} catch (Exception $e) {
			$this->_lastError = $e;
		}
		return false;
	}

	public function uninstall() {
		$this->_reset();
		try {
			return $this->deactivate()
				&& $this->_uninstallSchema()
				&& $this->_purgeSettings()
				&& $this->_purgeChangeLogCache()
				&& $this->_removeStorageDirectories()
				&& $this->_uninstallVersion();
		} catch (Exception $e) {
			$this->_lastError = $e;
		}
		return false;
	}

	/**
	 * Ensures all the plug-in's storage directories are created, 
	 *  as well as any required assets.
	 * If a directory exists, it is not re-created, nor is it purged.
	 * If a file asset exists, it is overwritten.
	 * 
	 * @return bool True if the operation succeeded, false otherwise
	 */
	public function ensureStorageDirectoriesAndAssets() {
		$this->_installStorageDirectoryAndAssets();
	}

	public function getRequiredPhpVersion() {
		return $this->_env->getRequiredPhpVersion();
	}

	public function getRequiredWpVersion() {
		return $this->_env->getRequiredWpVersion();
	}

	/**
	 * Returns the last occurred exception or null if none found.
	 * 
	 * @return \Exception The last occurred exception.
	 */
	public function getLastError() {
		return $this->_lastError;
	}

	private function _installStorageDirectoryAndAssets() {
		$result = false;
		if ($this->_ensureStorageDirectories()) {
			$result = $this->_installStorageDirsSecurityAssets();
		}
		return $result;
	}

	private function _readLookupDefinitions() {
		if ($this->_cachedDefinitions === null) {
			$definitions = array();
			$filePath = $this->_getLookupDefsFile();
			$categories = array(
				Abp01_Lookup::BIKE_TYPE,
				Abp01_Lookup::DIFFICULTY_LEVEL,
				Abp01_Lookup::PATH_SURFACE_TYPE,
				Abp01_Lookup::RAILROAD_ELECTRIFICATION,
				Abp01_Lookup::RAILROAD_LINE_STATUS,
				Abp01_Lookup::RAILROAD_OPERATOR,
				Abp01_Lookup::RAILROAD_LINE_TYPE,
				Abp01_Lookup::RECOMMEND_SEASONS
			);

			if (!is_readable($filePath)) {
				return null;
			}

			$prevUseErrors = libxml_use_internal_errors(true);
			$xml = simplexml_load_file($filePath, 'SimpleXMLElement');

			if ($xml) {
				foreach ($categories as $c) {
					$definitions[$c] = $this->_parseDefinitions($xml, $c);
				}
			} else {
				$this->_lastError = libxml_get_last_error();
				libxml_clear_errors();
			}

			libxml_use_internal_errors($prevUseErrors);
			$this->_cachedDefinitions = $definitions;
		}
		return $definitions;
	}

	private function _parseDefinitions($xml, $category) {
		$lookup = array();
		$node = $xml->{$category};
		if (empty($node) || empty($node->lookup)) {
			return array();
		}
		foreach ($node->lookup as $lookupNode) {
			if (empty($lookupNode['default'])) {
				continue;
			}
			$lookup[] = array(
				'default' => (string)$lookupNode['default'],
				'translations' => $this->_readLookupTranslations($lookupNode)
			);
		}

		return $lookup;
	}

	private function _readLookupTranslations($xml) {
		$translations = array();
		if (empty($xml->lang)) {
			return array();
		}
		foreach ($xml->lang as $langNode) {
			if (empty($langNode['code'])) {
				continue;
			}
			$tx = (string)$langNode;
			if (!empty($tx)) {
				$translations[(string)$langNode['code']] = $tx;
			}
		}
		return $translations;
	}

	private function _getLookupDefsFile() {
		$env = $this->_env;
		$dataDir = $env->getDataDir();

		if ($env->isDebugMode()) {
			$dirName = 'dev/setup';
			$testDir = sprintf('%s/%s', $dataDir, $dirName);
			if (!is_dir($testDir)) {
				$dirName = 'setup';
			}
		} else {
			$dirName = 'setup';
		}

		$filePath = sprintf('%s/%s/lookup-definitions.xml', $dataDir, $dirName);
		return $filePath;
	}

	private function _createCapabilities() {
		Abp01_Auth::getInstance()->installCapabilities();
		return true;
	}

	private function _removeCapabilities() {
		Abp01_Auth::getInstance()->removeCapabilities();
		return true;
	}

	private function _uninstallVersion() {
		delete_option(self::OPT_VERSION);
		return true;
	}

	private function _purgeSettings() {
		abp01_get_settings()->purgeAllSettings();
		return true;
	}

	private function _purgeChangeLogCache() {
		Abp01_ChangeLogDataSource_Cached::clearCache();
		return true;
	}

	private function _installData() {
		if (!$this->_installLookupData) {
			return true;
		}
		
		$db = $this->_env->getDb();
		$table = $this->_getLookupTableName();
		$langTable = $this->_getLookupLangTableName();
		$definitions = $this->_readLookupDefinitions();
		$ok = true;

		if (!$db || !is_array($definitions)) {
			return false;
		}

		//make sure table is empty
		$stats = $db->getOne($table, 'COUNT(*) AS cnt');
		if ($stats && is_array($stats) && $stats['cnt'] > 0) {
			return true;
		}

		//save lookup data
		foreach ($definitions as $category => $data) {
			if (empty($data)) {
				continue;
			}
			foreach ($data as $lookup) {
				$id = $db->insert($table, array(
					'lookup_category' => $category,
					'lookup_label' => $lookup['default']
				));

				$ok = $ok && $id !== false;
				if (!$ok) {
					break 2;
				}

				foreach ($lookup['translations'] as $lang => $label) {
					$ok = $ok && $db->insert($langTable, array(
						'ID' => $id,
						'lookup_lang' => $lang,
						'lookup_label' => $label
					)) !== false;
					if (!$ok) {
						break 3;
					}
				}
			}
		}

		return $ok;
	}

	private function _installDataTranslationsForLanguage($langCode) {
		$db = $this->_env->getDb();
		$table = $this->_getLookupTableName();
		$langTable = $this->_getLookupLangTableName();
		$definitions = $this->_readLookupDefinitions();

		foreach ($definitions as $category => $data) {
			if (empty($data)) {
				continue;
			}

			foreach ($data as $lookup) {
				$defaultLabel = $lookup['default'];

				$db->where('LOWER(lookup_label)', strtolower($defaultLabel));
				$db->where('lookup_category', $category);
				$id = intval($db->getValue($table, 'ID'));

				if (!is_nan($id) && $id > 0) {
					$db->where('ID', $id);
					$db->where('lookup_lang', $langCode);
					$test = $db->getOne($langTable, 'COUNT(*) as cnt');

					if ($test && is_array($test) && $test['cnt'] == 0) {
						$db->insert($langTable, array(
							'ID' => $id,
							'lookup_lang' => $langCode,
							'lookup_label' => $lookup['translations'][$langCode]
						));
					}
				}
			}
		}

		return true;
	}

	private function _installSchema() {
		$ok = true;
		$tables = array(
			$this->_getLookupTableDefinition(),
			$this->_getLookupLangTableDefinition(),
			$this->_getRouteDetailsTableDefinition(),
			$this->_getRouteTrackTableDefinition(),
			$this->_getRouteDetailsLookupTableDefinition()
		);

		foreach ($tables as $table) {
			$ok = $ok && $this->_createTable($table);
		}

		if (!$ok) {
			$this->_uninstallSchema();
		}

		return $ok;
	}

	private function _uninstallSchema() {
		return $this->_uninstallRouteDetailsTable() !== false &&
			$this->_uninstallRouteTrackTable() !== false &&
			$this->_uninstallLookupTable() !== false &&
			$this->_uninstallLookupLangTable() !== false &&
			$this->_uninstallRouteDetailsLookupTable() !== false;
	}

	private function _createTable($tableDef) {
		$db = $this->_env->getDb();
		if (!$db) {
			return false;
		}

		$charset = $this->_getDefaultCharset();
		$collate = $this->_getCollate();

		if (!empty($charset)) {
			$charset = "DEFAULT CHARACTER SET = '" . $charset . "'";
			$tableDef .= ' ' . $charset . ' ';
		}
		if (!empty($collate)) {
			$collate = "COLLATE = '" . $collate . "'";
			$tableDef .= ' ' . $collate . ' ';
		}

		$tableDef .= ' ';
		$tableDef .= 'ENGINE=MyISAM';

		$db->rawQuery($tableDef, null, false);
		$lastError = trim($db->getLastError());

		return empty($lastError);
	}

	private function _addLookupCategoryIndexToLookupTable() {
		$result = false;

		try {
			$db = $this->_env->getDb();
			$db->rawQuery('ALTER TABLE `' . $this->_getLookupTableName() .  '` ADD INDEX `lookup_category` (`lookup_category`)');
			$result = empty(trim($db->getLastError()));
		} catch (Exception $exc) {
			$result = false;
		}

		return $result;
	}

	private function _addRouteTrackFileMimeTypeColumnToRouteTrackTable() {
		$result = false;

		try {
			if (!$this->_routeTrackFileMimeTypeColumnExists()) {
				$db = $this->_env->getDb();
				$db->rawQuery("ALTER TABLE `" . $this->_getRouteTrackTableName() .  "` 
					ADD COLUMN route_track_file_mime_type VARCHAR(250) NOT NULL DEFAULT 'application/gpx' 
					AFTER route_track_file");
				$result = empty(trim($db->getLastError()));
			}
		} catch (Exception $exc) {
			$result = false;
		}

		return $result;
	}

	private function _routeTrackFileMimeTypeColumnExists() {
		$metaDb = $this->_env->getMetaDb();
		$metaDb->where('TABLE_NAME', $this->_getRouteTrackTableName())
			->where('COLUMN_NAME', 'route_track_file_mime_type');

		$columnCountRow = $metaDb->getOne('COLUMNS', 'COUNT(*) as COLUMN_COUNT');
		return $columnCountRow['COLUMN_COUNT'] > 0;
	}

	private function _getRouteTrackTableDefinition() {
		return "CREATE TABLE IF NOT EXISTS `" . $this->_getRouteTrackTableName() . "` (
			`post_ID` BIGINT(20) UNSIGNED NOT NULL,
			`route_track_file` LONGTEXT NOT NULL,
			`route_track_file_mime_type` VARCHAR(250) NOT NULL DEFAULT 'application/gpx' ,
			`route_min_coord` POINT NOT NULL,
			`route_max_coord` POINT NOT NULL,
			`route_bbox` POLYGON NOT NULL,
			`route_min_alt` FLOAT NULL DEFAULT '0',
			`route_max_alt` FLOAT NULL DEFAULT '0',
			`route_track_created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`route_track_modified_at` TIMESTAMP NULL DEFAULT NULL,
			`route_track_modified_by` BIGINT(20) NULL DEFAULT NULL,
				PRIMARY KEY (`post_ID`),
				SPATIAL INDEX `idx_route_track_bbox` (`route_bbox`)
		)";
	}

	private function _getRouteDetailsTableDefinition() {
		return "CREATE TABLE IF NOT EXISTS `" . $this->_getRouteDetailsTableName() . "` (
			`post_ID` BIGINT(10) UNSIGNED NOT NULL,
			`route_type` VARCHAR(150) NOT NULL,
			`route_data_serialized` LONGTEXT NOT NULL,
			`route_data_created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`route_data_last_modified_at` TIMESTAMP NULL DEFAULT NULL,
			`route_data_last_modified_by` BIGINT(20) NULL DEFAULT NULL,
				PRIMARY KEY (`post_ID`)
		)";
	}

	private function _getLookupLangTableDefinition() {
		return "CREATE TABLE IF NOT EXISTS `" . $this->_getLookupLangTableName() . "` (
			`ID` INT(10) UNSIGNED NOT NULL,
			`lookup_lang` VARCHAR(10) NOT NULL,
			`lookup_label` VARCHAR(255) NOT NULL,
				PRIMARY KEY (`ID`, `lookup_lang`)
		)";
	}

	private function _getLookupTableDefinition() {
		return "CREATE TABLE IF NOT EXISTS `" . $this->_getLookupTableName() . "` (
			`ID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			`lookup_category` VARCHAR(150) NOT NULL,
			`lookup_label` VARCHAR(255) NOT NULL,
				PRIMARY KEY (`ID`)
		)";
	}

	private function _getRouteDetailsLookupTableDefinition() {
		return "CREATE TABLE IF NOT EXISTS `" . $this->_getRouteDetailsLookupTableName() . "` (
			`post_ID` BIGINT(10) UNSIGNED NOT NULL,
			`lookup_ID` INT(10) UNSIGNED NOT NULL,
				PRIMARY KEY (`post_ID`, `lookup_ID`)
		)";
	}

	private function _uninstallRouteTrackTable() {
		$db = $this->_env->getDb();
		return $db != null ? $db->rawQuery('DROP TABLE IF EXISTS `' . $this->_getRouteTrackTableName() . '`', null, false) : false;
	}

	private function _uninstallRouteDetailsTable() {
		$db = $this->_env->getDb();
		return $db != null ? $db->rawQuery('DROP TABLE IF EXISTS `' . $this->_getRouteDetailsTableName() . '`', null, false) : false;
	}

	private function _uninstallLookupTable() {
		$db = $this->_env->getDb();
		return $db != null ? $db->rawQuery('DROP TABLE IF EXISTS `' . $this->_getLookupTableName() . '`', null, false) : false;
	}

	private function _uninstallLookupLangTable() {
		$db = $this->_env->getDb();
		return $db != null ? $db->rawQuery('DROP TABLE IF EXISTS `' . $this->_getLookupLangTableName() . '`', null, false) : false;
	}

	private function _uninstallRouteDetailsLookupTable() {
		$db = $this->_env->getDb();
		return $db != null ? $db->rawQuery('DROP TABLE IF EXISTS `' . $this->_getRouteDetailsLookupTableName() . '`', null, false) : false;
	}

	private function _reset() {
		$this->_lastError = null;
	}

	private function _getDefaultCharset() {
		return $this->_env->getDbCharset();
	}

	private function _getCollate() {
		return $this->_env->getDbCollate();
	}

	private function _getRouteTrackTableName() {
		return $this->_env->getRouteTrackTableName();
	}

	private function _getRouteDetailsTableName() {
		return $this->_env->getRouteDetailsTableName();
	}

	private function _getLookupLangTableName() {
		return $this->_env->getLookupLangTableName();
	}

	private function _getLookupTableName() {
		return $this->_env->getLookupTableName();
	}

	private function _getRouteDetailsLookupTableName() {
		return $this->_env->getRouteDetailsLookupTableName();
	}
}