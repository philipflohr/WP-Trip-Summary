<?php
/**
 * Copyright (c) 2014-2020 Alexandru Boia
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

trait RouteTrackTestDataHelpers {
    public function _generateRandomRouteTracks() {
        $postIds = array();
        $routeTracks = array();
        $faker = $this->_getFaker();

        $deltaLat = $faker->numberBetween(2, 10);
        $deltaLng = $faker->numberBetween(2, 10);

        for ($i = 0; $i < 10; $i++) {
            $postId = $this->_generatePostId($postIds);
            $postIds[] = $postId;
            
            $routeTracks[] = array(
                $this->_generateRandomRouteTrack($postId, $deltaLat, $deltaLng)
            );
        }

        return $routeTracks;
    }

    protected function _generateRandomRouteTrack($postId) {
        $faker = $this->_getFaker();
        
        if (func_get_args() == 3) {
            $deltaLat = func_get_arg(1);
            $deltaLng = func_get_arg(2);
        } else {
            $deltaLat = $faker->numberBetween(2, 10);
            $deltaLng = $faker->numberBetween(2, 10);
        }

        $minLat = $faker->randomFloat(5, -85, 85 - $deltaLat * 2);
        $minLng = $faker->randomFloat(5, -180, 180 - $deltaLng * 2);

        $maxLat = $minLat + $deltaLat;
        $maxLng = $minLng + $deltaLng;
        
        $fileName = $faker->uuid . '.gpx';

        $minAltitude = $faker->randomFloat(3, 0, 4000);
        $maxAltitude = $minAltitude + $faker->randomFloat(3, 0, 4000);

        $bbox = new Abp01_Route_Track_Bbox($minLat, 
            $minLng, 
            $maxLat, 
            $maxLng);

        return new Abp01_Route_Track($postId, 
            $fileName, 
            $bbox, 
            $minAltitude, 
            $maxAltitude);
    }

    abstract protected function _generatePostId($excludeAdditionalIds = null);
}