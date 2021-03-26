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

trait RouteTrackDocumentTestHelpers {
    protected function _determineExpectedDocumentData($testFiles, $testFileSpec) {
        if (!is_array($testFileSpec['expect'])) {
            $testFileSpecKey = $testFileSpec['expect'];
            $expectedData = $testFiles[$testFileSpecKey]['expect'];
        } else {
            $expectedData = $testFileSpec['expect'];
        }
        
        return $expectedData;
    }

    protected function _areDocumentWayPointsCorrect(Abp01_Route_Track_Document $actualDocument, $expectWaypoints) {
        $areWayPointsCorrect = true;

        foreach ($expectWaypoints as $expectWaypoint) {
            $areWayPointsCorrect = $this->_doesPointCollectionHavePoint($actualDocument->waypoints, $expectWaypoint);
            if (!$areWayPointsCorrect) {
                break;
            }
        }

        return $areWayPointsCorrect;
    }

    protected function _areAllTrackPartsCorrect(Abp01_Route_Track_Document $actualDocument, $expectTrackPartsSpec) {
        $allTrackPartsCorrect = false;
        $countExpectTrackParts = count($expectTrackPartsSpec);

        if ($actualDocument->parts !== null) {
            if (count($actualDocument->parts) == $countExpectTrackParts) {
                $allTrackPartsCorrect = true;
                for ($iPart = 0; $iPart < $countExpectTrackParts; $iPart++ ) {
                    $expectTrackPart = $expectTrackPartsSpec[$iPart];
                    $actualTrackPart = $actualDocument->parts[$iPart];
                    
                    $allTrackPartsCorrect = $this->_isTrackPartCorrect($actualTrackPart, $expectTrackPart);
                    if (!$allTrackPartsCorrect) {
                        break;
                    }
                }
            }
        }

        return $allTrackPartsCorrect;
    }

    protected function _isTrackPartCorrect(Abp01_Route_Track_Part $actualTrackPart, $expectTrackPartSpec) {
        $isTrackPartCorrect = false;

        if ($this->_doesTrackPartHaveCorrectName($actualTrackPart, $expectTrackPartSpec)) {
            $expectTrackLinesSpec = $expectTrackPartSpec['trackLines'];
            $countExpectTrackLines = count($expectTrackLinesSpec);

            if ($actualTrackPart->lines !== null) {
                if (count($actualTrackPart->lines) == $countExpectTrackLines) {
                    $isTrackPartCorrect = true;
                    for ($iLine = 0; $iLine < $countExpectTrackLines; $iLine++) {
                        $actualTrackLine = $actualTrackPart->lines[$iLine];
                        $expectTrackLineSpec = $expectTrackLinesSpec[$iLine];

                        $isTrackPartCorrect = !empty($actualTrackLine)
                            && $this->_doesLineHaveCorrectPoints($actualTrackLine, $expectTrackLineSpec);
            
                        if (!$isTrackPartCorrect) {
                            break;
                        }
                    }
                }
            }
        }

        return $isTrackPartCorrect;
    }

    protected function _doesTrackPartHaveCorrectName($actualTrackPart, $expectTrackPartSpec) {
        if (!empty($expectTrackPartSpec['name'])) {
            return $expectTrackPartSpec['name'] == $actualTrackPart->name;
        } else {
            return empty($actualTrackPart->name);
        }
    }

	protected function _doesLineHaveCorrectPoints(Abp01_Route_Track_Line $line, $expectTrackLineSpec) {
        $hasCorrectPoints = false;

		if (!empty($line->trackPoints)) {
			if (count($line->trackPoints) == $expectTrackLineSpec['trackPointsCount']) {
				$hasCorrectPoints = true;
				if (!empty($expectTrackLineSpec['sampleTrackPoints'])) {
					foreach ($expectTrackLineSpec['sampleTrackPoints'] as $expectTrackPointSpec) {
						$hasCorrectPoints = $this->_doesLineContainPoint($line, $expectTrackPointSpec);
						if (!$hasCorrectPoints) {
							break;
						}
					}
				}
			}
		}

        return $hasCorrectPoints;
    }

	protected function _doesLineContainPoint(Abp01_Route_Track_Line $line, $expectedPointSpec) {
        return $this->_doesPointCollectionHavePoint($line->trackPoints, 
            $expectedPointSpec);
    }

	protected function _doesPointCollectionHavePoint($points, $expectedPointSpec) {
        $found = false;

        $delta = isset($expectedPointSpec['delta']) 
            ? $expectedPointSpec['delta'] 
            : 0.00;

        foreach ($points as $candidatePoint) {
            if ($this->_candidatePointMatchesExpectedWithinDelta($candidatePoint, 
                    $expectedPointSpec, 
                    $delta)) {
                $found = true;
                break;
            }
        }

        return $found;
    }

	private function _candidatePointMatchesExpectedWithinDelta($candidatePoint, $expectedPointSpec, $delta) {
        return $this->_candidatePointLatLonMatchesExpectedWithinDelta($candidatePoint, 
                $expectedPointSpec, 
                $delta)
            && $this->_candidatePointElevationMatchesExpectedWithinDelta($candidatePoint, 
                $expectedPointSpec, 
                $delta);
    }

    private function _candidatePointLatLonMatchesExpectedWithinDelta($candidatePoint, $expectedPointSpec, $delta) {
        return abs($candidatePoint->coordinate->lat - $expectedPointSpec['lat']) / $expectedPointSpec['lat'] <= $delta 
            && abs($candidatePoint->coordinate->lng - $expectedPointSpec['lon']) / $expectedPointSpec['lon'] <= $delta;
    }

    private function _candidatePointElevationMatchesExpectedWithinDelta($candidatePoint, $expectedPointSpec, $delta) {
        if ($expectedPointSpec['ele'] != 0) {
            return abs($candidatePoint->coordinate->alt - $expectedPointSpec['ele']) / $expectedPointSpec['ele'] <= $delta;
        } else {
            return $candidatePoint->coordinate->alt == 0;
        }
    }
}