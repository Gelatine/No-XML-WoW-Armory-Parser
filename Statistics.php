<?
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

    /*
     *
     * This function takes in two parameters, a Statistic Page Number and
     * and a Statistic Name. This is currently the most efficent way to pull the
     * information from the WoW Roster.
     *
     * Statistic Page Numbers:
     * 131 - Character
     * 141 - Combat
     * 128 - Kills
     * 122 - Deaths
     * 133 - Quests
     * 14807 - Dungeons & Raids
     * 132 - Skills
     * 134 - Travel
     * 131 - Social
     * 21 - PvP
     *
     * To find a list of valid "Statistic Names" open a web browser to:
     * http://us.battle.net/wow/en/character/YOURSERVER/YOURCHARACTER/statistic
     * 
     * Click on any of the specific Statistic categories on the side bar(Character, 
     * Combat, Kills, etc.). Then copy any Statistic name. 
     * 
     * For example:
     * In Social(131), the Statistic Name would be "Number of hugs"
     *
     * The function would be called by:
     * getStatistic(131, "Number of hugs");
     *
     */
 
include_once('Functions.php');


class Statistics {

    /**
     * Given a Character Name, Server, and Statistic Page number, a URL for the Statistic 
     * page will be returned. 
     */
    private static function statisticURLBuilder($statPageNumber, $character, $server) {
            return Functions::getCharPageURL().$server.'/'.$character.'/statistic/'.$statPageNumber;
    }

    /**
     * Returns the Value of the requested Statistic. Please read the documentation at the
     * top of this class and above the function getStatistic in RosterAPI.php
     */
    public function getStatistic($statName, $statisticNumber, $character, $server) {
        
        $cacheFileName = Functions::getCacheDir().$statisticNumber.'_'.$character.'.html';
        $contents = Functions::getPageContents($this->statisticURLBuilder($statisticNumber, $character, $server), $cacheFileName);

        $dom = Functions::loadNewDom($contents);
        $xpath = new DomXPath($dom);
        $statistics = $xpath->query('//dl/dt');

        /*
         * Loop through every statistic until the static matching 
         * the query is located. Each loop increase the counter. The
         * $ctr variable will be used to find the associated value
         * of the statistic name. 
         */
        $ctr = 0;
        foreach($statistics as $s) {

            $currStatName = trim($s->textContent);

            if(addslashes($currStatName) == addslashes($statName)) {
                break;
            }

            $ctr++;
        }

        /*
         * Query the statistic value and return the $ctr\
         * statistic value.
         */
        $statValue = $xpath->query('//dl/dd');
        
        return trim($statValue->item($ctr)->nodeValue);


    }

    /*
     * Given an Array number, Character Name, and Server (Character Name and Server
     * are required inorder to pull the information), an Array of all the Statistic 
     * names wil be returned. 
     */
    public function getAllStatNames($statisticNumber, $character, $server) {
         
        $cacheFileName = Functions::getCacheDir().$statisticNumber.'_'.$character.'.html';

        $contents = Functions::getPageContents($this->statisticURLBuilder($statisticNumber, $character, $server), $cacheFileName);

        $dom = Functions::loadNewDom($contents);
        $xpath = new DomXPath($dom);
        $statistics = $xpath->query('//dl/dt');

        $statisticNames = array();

        foreach($statistics as $s) {

            $currStatName = trim($s->textContent);

            array_push($statisticNames, utf8_decode($currStatName));

        }

        return $statisticNames;
    
    }
}


?>
