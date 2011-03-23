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

/*
 * This RosterAPI class provides an API for the WoW Armory by parsing
 * the armory XHTML files. This class handles both Guild and
 * individual Character parsing. 
 *
 * Character:
 * Level, Race, Class, Achievement Points, Health, Power, 
 * Professions(names/values), Talents(names/points), 
 * Equipped Item Level, Equipped Items(name, enchants, level, gems),
 * Statistics, Glyphs
 *
 * Guild:
 * Names, Perks, Top Weekly Contributers, Size
 *
 * No additional libraries are needed other then what is included by 
 * default with __PHP5__. 
 *
 * PLEASE READ THE DOCUMENTATION ABOVE EACH FUNCTION FOR HELP ON HOW
 * TO USE EACH FUNCTION. 
 *
 * @author Josh Grochowski (josh[at]kastang[dot]com)
 */

header('Content-Type: text/html; charset=UTF-8');
include_once('Functions.php');
include('Statistics.php');
include('Glyphs.php');

class RosterAPI {

   /* 
     * The below values should not be modified. 
     */
    private $characterDom = null;
    private $character = null;
    private $server = null;
    private $guild = null;
    private $characterPage = null;

    private $statistics = null;
    private $glyphs = null;

    /**
     * The constructor requires a $server be specified
     * during the creation of the object. The $guild variable is
     * optional unless the guild specific functions in this class 
     * will be used. The $character variable is also optional. Both
     * the $guild and $character variable cannot be empty thoug because
     * the script would serve no purpose with both being null. The 
     * $server, $character, and $guild variables can be modified 
     * at any time.
     */
    public function __construct($server, $character='', $guild='') {

        /* 
         * Without $server being set, the correct guild and/or
         * character cannot be found. 
         */
        if(empty($server)) {
            throw new Exception("The Server Variable must be set.");
        } else {
            $this->server = $server;
        }

        /*
         * Without a Guild and Character name, the script serves no function. 
         */
        if(empty($guild) && empty($character)) {
            throw new Exception("A Guild Name And/Or Character Name must be set.");
        }

        /*
         * If the $guild variable is specified, set the
         * globar $guild variable. 
         */
        if(!empty($guild)) {
            $this->guild = rawurlencode($guild);
        }

        /*
         * If the character is set in the constructor, 
         * populate the $character and $characterDom
         * variable. 
         */
        if(!empty($character)) {
            $this->characterDom = new domDocument;
            $this->character = $character;
            $this->changeCharacter($character);
        }
           
        $this->statistics = new Statistics(); 
        $this->glyphs = new Glyphs();
    }

    /**
     * This function returns the URL for the Guild Summary page for user-set 
     * server and guild.
     */
    private function guildSummaryURLBuilder() {

        $guildSummary = Functions::getGuildPageURL().$this->server.'/'.$this->guild.'/';
        return $guildSummary;
    }

    /**
     * This function returns the URL for the Guild Perks page for user-set 
     * server and guild. 
     */
    private function guildPerksURLBuilder() {

        $guildPerk = Functions::getGuildPageURL().$this->server.'/'.$this->guild.'/perk';
        return $guildPerk;
    }

    /**
     * This function returns the URL for the Guild Roster page for user-set 
     * server and guild. 
     */
    private function guildRosterURLBuilder($pageNumber=1) {

        $guildPage = Functions::getGuildPageURL().$this->server.'/'.$this->guild.'/roster?page='.$pageNumber;
        return $guildPage;
    }

    /**
     * This function returns the URL for the Character page for user-set server
     * and charater. 
     */
    private function charPageURLBuilder() {

        $charPage = Functions::getCharPageURL().$this->server.'/'.$this->character.'/advanced';
        return $charPage;
    }

    /**
     * This function changes the character. It checks to see
     * if the new character is currently cached, if it is, it loads
     * the cached version of the file, otherwise it loads and saves
     * the newest file from the Armory servers.
     *
     * An exception is thron if the $character parameter is empty.  
     */
    public function changeCharacter($character) {

        if(empty($character)) {
            throw new Exception("Empty Character Name.");
        }

        $this->character = $character;
        $characterCacheFile = Functions::getCacheDir().$this->character.'.html';
        $this->characterPage = Functions::getPageContents($this->charPageURLBuilder(), $characterCacheFile);
       
        /*
         * If the WoW Armory returns a blank page, retry up to 5 times. 
         * TODO: Change this to use loadNewDom() function.
         */ 
        $retries = 5;
        while((empty($this->characterPage) && $retries>0)) {
            echo 'Retry';
            $this->characterPage = file_get_contents($this->charPageURLBuilder());
            $retries--;
        }

        $this->characterDom->loadHTML($this->characterPage);    
        $this->characterDom->preserveWhiteSpace = false;
    }

    /**
     * Changes the name of the server. 
     *
     * An exception is thrown if the $server parameter
     * is empty. 
     */
    public function changeServer($server) {

        if(empty($server)) {
            throw new Exception("Empty Server Name.");
        }
        
        $this->server = $server;
    }

    /**
     * Changes the name of the guild. 
     *
     * An exception is thrown if the $guild parameter
     * is empty. 
     */
    public function changeGuild($guild) {

        if(empty($guild)) {
            throw new Exception("Empty Guild Name.");
        }

        $this->guild = $guild;
    }

    /**
     * Returns the total number of characters in a guild. 
     */
    public function getGuildSize() {

        $guildCacheFile = Functions::getCacheDir().$this->guild.'_roster.html';
        $guildPage = Functions::getPageContents($this->guildRosterURLBuilder(), $guildCacheFile);
        $dom = Functions::loadNewDom($guildPage);
        $xpath = new DOMXPath($dom);
        $memberCount = $xpath->query('//strong[@class="results-total"]')->item(0)->nodeValue;

        return $memberCount;

    }

    /**
     * This function pulls every character name from the Guild Roster
     * and stores them in an array. If the Guild Roster is cached (and
     * withing caching time), the information will be pulled locally.
     * Otherwise, the information will be fetched from the WoW Armory 
     * servers. 
     *
     * The parameter $rank specifies if you also want to have the Rank
     * of each member added into the Return Array. The default value is
     * false. If the value is changed to true, an associative array will
     * be returned in the format of: "name" and "rank".
     *
     * The parameter $filterLevel will only return characters matching a 
     * specified level. By default the value is -1, meaning all characters
     * will be returned. 
     *
     * The array is returned. 
     */
    public function getGuildMembers($rank = false, $filterLevel=-1) {

        $guildArray = array();

        /*
         * The Roster only displays 100 characters per page. The total number of members
         * in the guild must first be determined before returning an array of all guild
         * members. 
         *
         * ---Thanks to Oldertarl for notifying me, and providing a solution of the WoW Roster change. 
         */
        $memberCount = $this->getGuildSize();

        for($ctr=1; $ctr<=ceil($memberCount/100); $ctr++) {

            $rosterCacheFile = Functions::getCacheDir().$this->guild.'_roster_'.$ctr.'.html';
            $rosterPage = Functions::getPageContents($this->guildRosterURLBuilder($ctr), $rosterCacheFile);

            $dom = Functions::loadNewDom($rosterPage);
            $roster = $dom->getElementsByTagName('tbody');
            $char = $roster->item(0)->getElementsByTagName('tr');

            foreach ($char as $c) {

                $charInfo = $c->getElementsByTagName('td');
                $charName = $charInfo->item(0)->nodeValue;
       
                if($filterLevel != -1) {
                    $level = $charInfo->item(3)->nodeValue;
                }
     
                if(!$rank) { 
                    
                    if($filterLevel != -1) {
                        
                        if($level == $filterLevel) {
                            array_push($guildArray, utf8_decode($charName));
                        }

                    } else {

                        array_push($guildArray, utf8_decode($charName));

                    }

                } else {

                     if($filterLevel != -1) {
                        
                        if($level == $filterLevel) {
                            $guildArray[utf8_decode($charName)] =
                                substr(trim($charInfo->item(4)->nodeValue), -1);
                        }

                    } else {

                        $guildArray[utf8_decode($charName)] =
                                substr(trim($charInfo->item(4)->nodeValue), -1);

                    }

              
                }
            }
        }

        return $guildArray;
    }

    /**
     * This function will return an array of all the perks the user-set
     * guild currently has. If the Guild Perks file is cached (and within 
     * cache time), the file will be read locally. Otherwise, the newest
     * copy will be pulled from the WoW Armory. 
     */
    public function getGuildPerks() {

        $perksArray = array();
        $perksCacheFile = Functions::getCacheDir().$this->guild.'_perks.html';
        $perksPage = Functions::getPageContents($this->guildPerksURLBuilder(), $perksCacheFile);
        $dom = Functions::loadNewDom($perksPage);
        $xpath = new DOMXPath($dom);
       
        //p1, p2, p3,..., pn 
        $index = 1;
        $p = "p";

        $guildLevel = $xpath->query('//span[@class="level"]/strong')->item(0)->nodeValue;

        while($index < $guildLevel) {

            $perk = $xpath->query('//li[@id="'.$p.$index.'"]/div/strong');
            array_push(utf8_decode($perksArray, $perk->item(0)->nodeValue));
            $index++;
        }

        return $perksArray;
    }

    /**
     * The function returns the top 5 weekly guild contributers. 
     * If the Guild Summary file is cached (and within cache time),
     * the file will be read locally. Otherwise, the newest
     * copy will be pulled from the WoW Armory. 
     */
    public function getTopWeeklyContributers() {

        $contribArray = array();
        $contribCacheFile = Functions::getCacheDir().$this->guild.'_contrib.html';
        $contribPage = Functions::getPageContents($this->guildSummaryURLBuilder(), $contribCacheFile);
        $dom = Functions::loadNewDom($contribPage);
        $xpath = new DOMXPath($dom);
        $contrib = $xpath->query('//td[@class="name"]/a');
        
        $MAX_CONTRIB = 5;
        $counter = 0;

        while($counter < $MAX_CONTRIB) {

            array_push($contribArray, utf8_decode($contrib->item($counter)->nodeValue));

            $counter++;
        }

        return $contribArray;
    }

    /**
     * Returns an integer value representing the Gender of the character. 
     * 0 = Male, 1 = Female. 
     *
     * This method works a little different then the rest of the Character 
     * 'getter' functions. The WoW Armory does not provide gender on the 
     * individual character page, rather it only provides an image of the gender
     * on the main roster page. This method will parse the Roster page for the URL
     * containing the image of the gender and return male or female. 
     *
     */
    public function getGender() {

        /**
         * Get the total number of members in the guild. 
         */

        $memberCount = $this->getGuildSize();
        for($ctr=1; $ctr<=ceil($memberCount/100); $ctr++) {

            $rosterCacheFile = Functions::getCacheDir().$this->guild.'_roster_'.$ctr.'.html';
            $rosterPage = Functions::getPageContents($this->guildRosterURLBuilder($ctr), $rosterCacheFile);

            $dom = Functions::loadNewDom($rosterPage);
            $roster = $dom->getElementsByTagName('tbody');
            $char = $roster->item(0)->getElementsByTagName('tr');


            /*
             * Loop through every character in the guild until the character
             * is found. When it is found, return the gender Id based off the
             * image link on the Roster. 
             */ 
            foreach ($char as $c) {

                $charInfo = $c->getElementsByTagName('td');
                $charImages = $c->getElementsByTagName('img');

                if(strtolower(utf8_decode($charInfo->item(0)->nodeValue)) == strtolower($this->character)) {
                    return substr($charImages->item(0)->getAttribute('src'),-5,1);
                }

            }
        }

        return null;

    }

    /**
     * Returns the Equipped Gear iLvl of the
     * Character. 
     */
    public function getItemLevel() {

        $xpath = new DOMXPath($this->characterDom);
        $iLvl = $xpath->query('//span[@class="equipped"]');

        return $iLvl->item(0)->nodeValue;
    }


    /**
     * Returns the Level of the Character. 
     */
    public function getLevel() {

        $xpath = new DOMXPath($this->characterDom);
        $level = $xpath->query('//span[@class="level"]');

        return $level->item(0)->nodeValue;
    }

    /**
     * Returns the Class of the Character. 
     */
    public function getClass() {

        $xpath = new DOMXPath($this->characterDom);
        $class = $xpath->query('//a[@class="class"]');

        return $class->item(0)->nodeValue;
    }

    /**
     * Returns the Race of the Character. 
     */
    public function getRace() {

        $xpath = new DOMXPath($this->characterDom);
        $race = $xpath->query('//a[@class="race"]');

        return $race->item(0)->nodeValue;
    }

    /**
     * Returns the Achievement Points of the Character. 
     */
    public function getAchievementPoints() {
        
        $xpath = new DOMXPath($this->characterDom);
        $ap = $xpath->query('//div[@class="achievements"]/a');
        
        return $ap->item(0)->nodeValue;

    }

    /**
     * Returns an associative array of names and levels of 
     * both professions. 
     * 
     * The returned array can be accessed by the following:
     *
     * Profession 1: Name: "profName1", Level: "profValue1"
     * Profession 2: Name: "profName2", Level: "ProfValue2"
     */
    public function getProfessions() {

        $xpath = new DOMXPath($this->characterDom);

        $profName = $xpath->query('//span[@class="profession-details"]/span[@class="name"]');
        $profValue = $xpath->query('//span[@class="profession-details"]/span[@class="value"]');

        /*
         * If the character does not have either profession, or is missing a profession
         * the value returned is "No Profession". Instead of returning "No Profession",
         * the following if statements will find such occurances and replace them with an
         * empty string.
         */
        if($profName->item(0)->nodeValue == $profName->item(1)->nodeValue) {

            $profArray = array("profName1" => "",
                                "profValue1" => $profValue->item(0)->nodeValue,
                                "profName2" => "",
                                "profValue2" => $profValue->item(1)->nodeValue);

        } else if($profName->item(0)->nodeValue == "No profession") {

            $profArray = array("profName1" => "",
                                "profValue1" => $profValue->item(0)->nodeValue,
                                "profName2" => $profName->item(1)->nodeValue,
                                "profValue2" => $profValue->item(1)->nodeValue);

        } else if($profName->item(1)->nodeValue == "No profession") {

            $profArray = array("profName1" => $profName->item(0)->nodeValue,
                                "profValue1" => $profValue->item(0)->nodeValue,
                                "profName2" => "",
                                "profValue2" => $profValue->item(1)->nodeValue);

        } else {

            $profArray = array("profName1" => $profName->item(0)->nodeValue,
                                "profValue1" => $profValue->item(0)->nodeValue,
                                "profName2" => $profName->item(1)->nodeValue,
                                "profValue2" => $profValue->item(1)->nodeValue);
        }

        return $profArray;
    }

    /**
     * Returns an associative array containing the values of Talent Tree 1 and 
     * Talent Tree 2. 
     *
     * If the character does not have any talents selected for one of the trees, 
     * the name and talent points will return an empty string. 
     * 
     * The returned array is in the format of:
     * Talent Tree 1: Name: talent1 Value: talent1points
     * Talent Tree 2: Name: talent2 Value: talent2points
     */
    public function getTalents() {

        $xpath = new DOMXPath($this->characterDom);

        $talents = $xpath->query('//span[@class="name-build"]/span[@class="name"]');
        $talentPoints = $xpath->query('//span[@class="name-build"]/span[@class="build"]');


        /*
         * If a character does not have both talents set, the WoW Armory will return
         * the string value "Talents" for the empty talent. The below is a check to 
         * make sure "Talents" is not returned, instead an empty string will be in the
         * returned array.
         */

        if(($talents->item(0)->nodeValue == "Talents") && ($talents->item(1)->nodeValue == "Talents")) {

            $talentArray = array("talent1" => "",
                                    "talent1points" => "",
                                    "talent2" => "",
                                    "talent2points" => "");

        } else if($talents->item(0)->nodeValue == "Talents") {
        
            $talentArray = array("talent1" => "",
                                    "talent1points" => "",
                                    "talent2" => $talents->item(1)->nodeValue,
                                    "talent2points" => $talentPoints->item(1)->nodeValue);

        } else if($talents->item(1)->nodeValue == "Talents") {

            $talentArray = array("talent1" => $talents->item(0)->nodeValue,
                                    "talent1points" => $talentPoints->item(0)->nodeValue,
                                    "talent2" => "",
                                    "talent2points" => "");
        } else {

            $talentArray = array("talent1" => $talents->item(0)->nodeValue,
                                    "talent1points" => $talentPoints->item(0)->nodeValue,
                                    "talent2" => $talents->item(1)->nodeValue,
                                    "talent2points" => $talentPoints->item(1)->nodeValue);

        }

        return $talentArray;

    }

    /**
     * Returns the Health of the character. 
     */
    public function getHealth() {
        $xpath = new DOMXPath($this->characterDom);
        $health = $xpath->query('//li[@class="health"]/span[@class="value"]');

        return $health->item(0)->nodeValue;
    }

    /**
     * Returns the Power (mana, etc) of the character.
     */
    public function getPower() {
        $xpath = new DOMXPath($this->characterDom);
        $power = $xpath->query('//li[@id="summary-power"]/span[@class="value"]');

        return $power->item(0)->nodeValue;
    }

    /**
     * This function takes in a stat and returns the name and value.A
     * 
     * The parameter should take in one of the stats listed below:
     *
     * strength, agility, stamina, intellect,
     * spirit, mastery, meleedamage, meleedps,
     * meleeattackpower, meleespeed, meleehaste,
     * meleehit, meleecrit, meleecrip, expertise,
     * rangeddamage, rangeddps, rangedattackpower,
     * rangedspeed, rangedhaste, rangedhit, rangedcrit,
     * spellpower, spellhaste, spellhit, spellcrit,
     * spellpenetration, manaregen, combatregen, armor,
     * dodge, parry, block, resilience, arcaneres, fireres,
     * frostres, natureres, shadowres,
     *
     * Throws an exception if the $stat parameter is empty. 
     */
    public function getStat($stat) {

        if(empty($stat)) {
            throw new Exception("Empty Stat.");
        }

        $xpath = new DOMXPath($this->characterDom);

        $statName = $xpath->query('//li[@data-id="'.$stat.'"]/span[@class="name"]');
        $statValue = $xpath->query('//li[@data-id="'.$stat.'"]/span[@class="value"]');

        
        $statArray = array("name" => utf8_decode($statName->item(0)->nodeValue),
                            "value" => utf8_decode($statValue->item(0)->nodeValue));

        return $statArray;
    }

    /**
     * Returns an Associative Array of the items currently equipped 
     * on the character along with the enchants on each item, an Array
     * of Gem Names, and the item level. 
     *
     * The Associative Array uses the following names:
     * "name" = The Name of the Item. 
     * "level" = iLvl of the Item. 
     * "enchant" = The Name of the Enchant on the Item.
     * "gem"[INDEX_NUMBER] = An array of Gems equipped to the item, 
     * the INDEX_NUMBER will range from 0..n depending on the number
     * of equipped Gems. 
     *
     * NOTE: THIS METHOD HAS A HIGH POTIENTIAL OF OVERFLOWING THE PAGE 
     * REQUEST LIMIT ON WOW SERVERS BECAUSE OF HOW THE GEM DATA MUST BE
     * PULLED. USE THIS METHOD SPARINGLY THE FIRST TIME IT IS RAN, THE
     * ITEMS WILL BE CACHED AFTER THE FIRST RUN. 
     *
     * The Index of the array corresponds to 
     * 
     * 0 - Head
     * 1 - Neck
     * 2 - Shoulder
     * 3 - Shirt
     * 4 - Chest
     * 5 - Belt
     * 6 - Legs
     * 7 - Feet
     * 8 - Bracers
     * 9 - Gloves
     * 10 - Ring #1
     * 11 - Ring #2
     * 12 - Trinket #1
     * 13 - Trinket #2
     * 14 - Cloak
     * 15 - Main Weapon 
     * 16 - Offhand/Secondary Weapon
     * 17 - Wand
     * 18 - Tabard
     *
     * If the character does not have an item equipped, an empty
     * entry will be added to the index to preserve the index numbering. 
     * Items without enchants will return an empty entry for the Enchant. 
     *
     */
    public function getItems() {

        $itemArray = array();
        $xpath = new DOMXPath($this->characterDom);

        /*
         * Characters can equipt 19 different items. 
         */
        $MAX_ITEMS = 18;

        /*
         * Generates the Array adding every item in the order expressed
         * above.
         */
        for($i=0; $i<=$MAX_ITEMS; $i++) {

            $itemName = $xpath->query('//div[@data-id="'.$i.'"]/div[@class="slot-inner"]/div[@class="slot-contents"]/div[@class="details"]/span[@class="name-shadow"]');

            $itemEnchant = $xpath->query('//div[@data-id="'.$i.'"]/div[@class="slot-inner"]/div[@class="slot-contents"]/div[@class="details"]/span[@class="enchant-shadow"]');

            $itemLevel = $xpath->query('//div[@data-id="'.$i.'"]/div[@class="slot-inner"]/div[@class="slot-contents"]/div[@class="details"]/span[@class="level"]');

            $itemGems = $xpath->query('//div[@data-id="'.$i.'"]/div[@class="slot-inner"]/div[@class="slot-contents"]/div[@class="details"]/span[@class="sockets"]/span/a/@href');


            /*
             * Items can have multiple Gems. The WoW Armory only returns
             * the link to the gem item. After gathering all of the gems
             * for a particular item, each link must grab another HTML
             * file and the name of the Gem must be parsed from it. 
             */
            $tmpGemArray = array();
            foreach($itemGems as $gems) {
                array_push($tmpGemArray, $gems->nodeValue);
            }


            /*
             * Now that we have all of the links, we need to parse 
             * every file and pull the name and store it into another
             * array. 
             */
            $gemNameArray = array();

            foreach($tmpGemArray as $gemLink) {

                $gemId = explode('/', $gemLink);
                
                //TODO: Add Link Builder. 
                $link = "http://us.battle.net".$gemLink;

                /*
                 * Checks to see if the Gem has been cached, this will
                 * lower requests and speed up result times. 
                 */
                $gemCacheFile = Functions::getCacheDir().$gemId[4].'.html';
                if(Functions::isCached($gemCacheFile, true)) {

                    $gemPage = file_get_contents($gemCacheFile);

                } else {

                    $gemPage = file_get_contents($link);
                    file_put_contents($gemCacheFile, $gemPage);
                }


                $dom = Functions::loadNewDom($gemPage);
                $gXP = new DOMXPath($dom);

                /*
                 * The easiest way to pull the Gem name is via the
                 * <title> tag on the Gems Page. 
                 */
                $gn = $gXP->query('//title');
                $gemName = explode("-", $gn->item(0)->nodeValue);

                array_push($gemNameArray, utf8_decode($gemName[0]));

            }

            /*
             * PHP will shoot notices if there are no entries matching
             * a particular query. Example, An item not equipped, no enchants
             * on an item, etc. The warnings are supressed. 
             */
            $tmp = array(

                    "name" => utf8_decode(@$itemName->item(0)->nodeValue),
                    "level" => @$itemLevel->item(0)->nodeValue,
                    "enchant" => trim(utf8_decode(@$itemEnchant->item(0)->nodeValue)),
                    "gems" => @$gemNameArray);

            /*
             * If the Item is not equipped on the character, we want an empty 
             * array returned. 
             */
            if(empty($itemName->item(0)->nodeValue)) {
                array_push($itemArray, "");
            } else {
                array_push($itemArray, $tmp);
            } 
        }

        return $itemArray;
    }

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
     public function getStatistic($statisticNumber, $statisticName) {

        return $this->statistics->getStatistic($statisticName, $statisticNumber, $this->character, $this->server);

     }


    /*
     * This function takes in a statistic number (see getStatistics for a list of valid numbers)
     * and will return an Array of all Statistic names. 
     */
    public function getAllStatNames($statisticNumber) {

       return $this->statistics->getAllStatNames($statisticNumber, $this->character, $this->server);

    }

    /**
     * This function will return an Associative Array of Glyphs for the
     * character. 
     *
     * The Associative Array format is:
     * "name" - Name of Glyph
     * "type" - Major, Minor, or Prime Glyph
     * "url" - URL of the Glyph
     * "itemNumber" - Unique item number of the Glyph. 
     */
    public function getGlyphs() {
        return $this->glyphs->getGlyphs($this->character, $this->server);
    }
}

?>
