<?php
    /*
     * Copyright (c) 2010 Josh Grochowski (josh[at]kastang[dot]com)
     *
     * Permission is hereby granted, free of charge, to any person obtaining a copy
     * of this software and associated documentation files (the "Software"), to deal
     * in the Software without restriction, including without limitation the rights
     * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
     * copies of the Software, and to permit persons to whom the Software is
     * furnished to do so, subject to the following conditions:
     *
     * The above copyright notice and this permission notice shall be included in
     * all copies or substantial portions of the Software.
     *
     * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
     * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
     * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
     * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
     * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
     * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
     * THE SOFTWARE.
     */

include_once('Functions.php');

class Glyphs {

    /**
     * Given a Character and Server, builds the Talent Page URL. 
     */
    private static function talentURLBuilder($character, $server) {
            return Functions::getCharPageURL().$server.'/'.$character.'/talent/primary';
    }

    /**
     * This function will return an associative array of all Glyphs equipped on the character.
     *
     * Associative Array Format:
     * "name" - Name of Glyph
     * "type" = Major, Minor, or Prime
     * "url" - URL of the Glyph
     * "itemNumber" - Unique item number of the Glyph.
     *
     * @return - An Associative Array. 
     */
    public function getGlyphs($character, $server) {

        $cacheFileName = Functions::getCacheDir().'talents_'.$character.'.html';
        $contents = Functions::getPageContents($this->talentURLBuilder($character, $server), $cacheFileName);

        $dom = Functions::loadNewDom($contents);
        $xpath = new DomXPath($dom);

        $glyphTypes = array("major", "minor", "prime");
        $glyphArray = array();

        foreach($glyphTypes as $gT) {

            $glyphNames = $xpath->query('//div[@class="character-glyphs-column glyphs-'.$gT.'"]/ul/li[@class="filled"]/a/span[@class="name"]');
            $glyphLinks = $xpath->query('//div[@class="character-glyphs-column glyphs-'.$gT.'"]/ul/li[@class="filled"]/a/@href');

            $ctr = 0;
            foreach($glyphNames as $g) {

                $itemNumber = explode("/", $glyphLinks->item($ctr)->nodeValue);

                $tmpArray = array(
                    "name" => trim(utf8_decode($g->nodeValue)),
                    "type" => $gT,
                    "url" => $glyphLinks->item($ctr)->nodeValue,
                    "itemNumber" => $itemNumber[4] );

                array_push($glyphArray, $tmpArray);

                $ctr++;
            }
        }

        return $glyphArray;
    }
}

?>
