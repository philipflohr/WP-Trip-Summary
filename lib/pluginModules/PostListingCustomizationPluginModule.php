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

class Abp01_PluginModules_PostListingCustomizationPluginModule extends Abp01_PluginModules_PluginModule {
    public function __construct(Abp01_Env $env, Abp01_Auth $auth) {
        parent::__construct($env, $auth);
    }

    public function load() {
        $this->_registerWebPageAssets();
        $this->_registerPostListingCustomizations();
    }

    private function _registerWebPageAssets() {
        add_action('admin_enqueue_scripts', array($this, 'onAdminEnqueueStyles'));
    }

    public function onAdminEnqueueStyles() {
        if ($this->_shouldAddPostListingStyles()) {
            Abp01_Includes::includeStyleAdminPostsListing();
        }
    }

    private function _shouldAddPostListingStyles() {
        return $this->_env->isListingWpPosts();
    }

    private function _registerPostListingCustomizations() {
        foreach ($this->_getPostListingCustomizations() as $customization) {
            $customization->apply();
        }
    }

    private function _getPostListingCustomizations() {
        return array(
            new Abp01_Display_PostListing_TripSummaryStatusColumnsDecorator()
        );
    }
}