<?php
/**
 * Copyright (c) 2014-2021 Alexandru Boia
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

class Abp01_PluginModules_AboutPagePluginModule extends Abp01_PluginModules_PluginModule {
	/**
	 * @var Abp01_View
	 */
	private $_view;

	public function __construct(Abp01_View $view, Abp01_Env $env, Abp01_Auth $auth) {
		parent::__construct($env, $auth);
		$this->_view = $view;
	}

	public function load() {
		$this->_registerMenuHook();
		$this->_registerWebPageAssets();
	}

	public function registerAdditionalPluginHeaders($extraHeaders) {
		return array_merge($extraHeaders, array(
			'WPTSVersionName' => 'WPTS Version Name'
		));
	}

	private function _registerMenuHook() {
		add_action('admin_menu', array($this, 'onAddAdminMenuEntries'));
	}

	public function onAddAdminMenuEntries() {
		add_submenu_page(
			ABP01_MAIN_MENU_SLUG, 
			esc_html__('About WP Trip Summary', 'abp01-trip-summary'), 
			esc_html__('About', 'abp01-trip-summary'), 
			Abp01_Auth::CAP_MANAGE_TRIP_SUMMARY, 
			ABP01_ABOUT_SUBMENU_SLUG, 
			array($this, 'displayAdminAboutPage'));
	}

	public function displayAdminAboutPage() {
		$data = new stdClass();
		$data->pluginLogoPath = $this->_getPluginLogoPath();
		$data->pluginData = $this->_getPluginData();
		$data->envData = $this->_getEnvData();
		$data->changelog = $this->_readChangeLog();
		echo $this->_view->renderAdminAboutPage($data);
	}

	private function _getPluginLogoPath() {
		return $this->_env->getPluginAssetUrl('media/img/logo.png');
	}

	private function _getPluginData() {
		$this->_registerAdditionalPluginHeadersProvider();
		return get_plugin_data(ABP01_PLUGIN_MAIN);
	}

	private function _getEnvData() {
		return array(
			'CurrentWP' => $this->_env->getWpVersion(),
			'CurrentPHP' => $this->_env->getPhpVersion()
		);
	}

	private function _registerAdditionalPluginHeadersProvider() {
		add_filter('extra_plugin_headers', 
			array($this, 'registerAdditionalPluginHeaders'));
	}

	private function _readChangeLog() {
		$filePath = $this->_determineReadmeTxtFilePath();
		$extractor = new Abp01_ReadmeChangelogExtractor($filePath);
		return $extractor->extractChangeLog();
	}

	private function _determineReadmeTxtFilePath() {
		return ABP01_PLUGIN_ROOT . '/readme.txt';
	}

	private function _registerWebPageAssets() {
		add_action('admin_enqueue_scripts', 
			array($this, 'onAdminEnqueueStyles'));

		add_action('admin_enqueue_scripts', 
			array($this, 'onAdminEnqueueScripts'));
	}

	public function onAdminEnqueueStyles() {
		if ($this->_shouldEnqueueWebPageAssets()) {
			Abp01_Includes::includeStyleAdminAbout();
		}
	}

	private function _shouldEnqueueWebPageAssets() {
		return $this->_isViewingAboutPage() ;
	}

	private function _isViewingAboutPage() {
		return $this->_env->isAdminPage(ABP01_ABOUT_SUBMENU_SLUG);
	}

	public function onAdminEnqueueScripts() {
		if ($this->_shouldEnqueueWebPageAssets()) {
			
		}
	}
}