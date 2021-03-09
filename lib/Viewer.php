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

class Abp01_Viewer {
    const TAB_INFO = 'abp01-tab-info';

    const TAB_MAP = 'abp01-tab-map';

    /**
     * @var Abp01_View
     */
    private $_view;

    /**
     * @var stdClass
     */
    private $_data;

    private $_renderedParts = null;

    public function __construct(Abp01_View $view, stdClass $data) {
        $this->_view = $view;
        $this->_data = $data;
    }

    public static function getAvailableTabs() {
        return array(
            self::TAB_INFO => __('Prosaic details', 'abp01-trip-summary'), 
            self::TAB_MAP => __('Map', 'abp01-trip-summary')
        );
    }

    public static function isTabSupported($tab) {
        return in_array($tab, array_keys(self::getAvailableTabs()));
    }

    public function render() {
        if ($this->_renderedParts === null) {
            $viewerHtml = null;
			$teaserHtml = null;
	
			//render the teaser and the viewer and attach the results to the post content
			if ($this->_canBeRendered()) {
				$teaserHtml = $this->_view->renderFrontendTeaser($this->_data);
				$viewerHtml = $this->_view->renderFrontendViewer($this->_data);
			}
	
			$this->_renderedParts = array(
				'teaserHtml' => $teaserHtml,
				'viewerHtml' => $viewerHtml
			);
        }

        return $this->_renderedParts;
    }

    private function _canBeRendered() {
        return $this->_data->info->exists 
            || $this->_data->track->exists;
    }

    public function renderAndAttachToContent($postContent) {
        $viewerContentParts = $this->render();
        $postContent = $viewerContentParts['teaserHtml'] . $postContent;
	
		if (!$this->_contentHasAnyTypeOfShortCode($postContent)) {
			$postContent = $postContent . $viewerContentParts['viewerHtml'];
		} elseif ($this->_contentHasViewerShortcode($postContent)) {
			//Replace all but on of the shortcode references
			$postContent = $this->_ensureContentHasUniqueShortcode($postContent);
		}
	
		return $postContent;
    }

    private function _contentHasAnyTypeOfShortCode(&$postContent) {
        return $this->_contentHasViewerShortcode($postContent) 
            || $this->_contentHasViewerShortCodeBlock($postContent);
    }

    private function _contentHasViewerShortcode(&$postContent) {
		return preg_match($this->_getViewerShortcodeRegexp(), $postContent);
	}

	private function _getViewerShortcodeRegexp() {
		return '/(\[\s*' . ABP01_VIEWER_SHORTCODE . '\s*\])/';
	}
	
	private function _contentHasViewerShortCodeBlock(&$postContent) {
		return function_exists('has_block') 
			&& has_block('abp01/block-editor-shortcode', $postContent);
	}

	private function _ensureContentHasUniqueShortcode(&$postContent) {
		$replaced = false;
		return preg_replace_callback($this->_getViewerShortcodeRegexp(), 
			function($matches) use (&$replaced) {
				if ($replaced === false) {
					$replaced = true;
					return $this->_getViewerShortcode();
				} else {
					return '';
				}
			}, $postContent);
	}

    private function _getViewerShortcode() {
        return '[' . ABP01_VIEWER_SHORTCODE . ']';
    }
}