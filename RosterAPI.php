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
 * This RosterAPI class provides an API for the WoW Armory by parsing
 * the armory XHTML files. This class handles both Guild and
 * individual Character parsing. 
 *
 * Character:
 * Level, Race, Class, Achievement Points, Health, Power, 
 * Professions(names/values), Talents(names/points). 
 *
 * Guild:
 * Names, Perks, Top Weekly Contributers. 
 *
 * No additional libraries are needed other then what is included by 
 * default with PHP5. 
 *
 * @author Josh Grochowski (josh[at]kastang[dot]com)
 */

class RosterAPI {

    /*
     * The $CACHETIME variable can be modified depending on how
     * often you want to pull new information from the Roster. 
     *
     * Recommended: A value greater >= 3600.
     *
     * Not Recommended: To turn off Cacheing, set $CACHETIME to
     * -1. If this script will be ran often, you risk the chance
     * being banned from the WoW Armory for exceeding the maximum
     * number of requests per minute/hour/day. 
     *
     * The default $CACHETIME value is 5 Hours.
     * (60 seconds * 60 minutes)*5. 
     */
    private $CACHETIME = 18000;

    /*
     * The $CACHEDIR variable must be the absolute path to 
     * a desired cache directory. The directory must have write
     * permissions (chmod 777 the directory). The path should also 
     * contain a trailing slash ('/'). 
     * 
     * Example: /path/to/cache/directory/
     */
    private $CACHEDIR = '/home/kastang/projects/rosterAPI/cache/';

    /*
     * The $CHAR_PAGE_URL and $GUILD_PAGE_URL variables should not need
     * to be altered if you are pulling from the US WoW Armory. In theory
     * the URL can be modified for the Europen WoW Armory by changeing
     * 'us' to 'eu', but functionality HAS NOT BEEN TESTED ON EUROPEAN
     * SERVERS. 
     */
    private $CHAR_PAGE_URL = "http://us.battle.net/wow/en/character/";
    private $GUILD_PAGE_URL = "http://us.battle.net/wow/en/guild/";

    /* 
     * The below values should not be modified. 
     */
    private $characterDom = null;
    private $character = null;
    private $server = null;
    private $guild = null;
    private $characterPage = null;

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
    }

    /**
     * This function returns the URL for the Guild Summary page for user-set 
     * server and guild.
     */
    private function guildSummaryURLBuilder() {

        $guildSummary = $this->GUILD_PAGE_URL.$this->server.'/'.$this->guild.'/';
        return $guildSummary;
    }

    /**
     * This function returns the URL for the Guild Perks page for user-set 
     * server and guild. 
     */
    private function guildPerksURLBuilder() {

        $guildPerk = $this->GUILD_PAGE_URL.$this->server.'/'.$this->guild.'/perk';
        return $guildPerk;
    }

    /**
     * This function returns the URL for the Guild Roster page for user-set 
     * server and guild. 
     */
    private function guildRosterURLBuilder() {

        $guildPage = $this->GUILD_PAGE_URL.$this->server.'/'.$this->guild.'/roster';
        return $guildPage;
    }

    /**
     * This function returns the URL for the Character page for user-set server
     * and charater. 
     */
    private function charPageURLBuilder() {

        $charPage = $this->CHAR_PAGE_URL.$this->server.'/'.$this->character.'/simple';
        return $charPage;
    }
   
    /**
     * Given a path to a file, this function first checks if
     * the file exists, if it has, it checks to see if it has
     * been modified within the caching time. If both of the
     * above criteras have been met, true is returned. Otherwise, 
     * false is returned to the requesting runction. 
     *
     * An exception will be thrown if the $file variable is empty. 
     */ 
    private function isCached($file) {

        //Verifies the file path is not empty. 
        if(empty($file)) {
            throw new Exception("Empty File Name.");
        }

        //If the file already exists and it is within the cache window, 
        //return true. Otherwise, return false. 
        if(file_exists($file)) {

            $timeSinceModified = strtotime("now") - filemtime($file);

            if($timeSinceModified > $this->CACHETIME) {

                return false;

            } else {

                return true;
            }

        } else {

            return false;

        }

    }
    
    /**
     * Given an HTML page, loads the HTML into a domDocument and
     * returns the 'object' to the requesting method. 
     */
    private function loadNewDom($domDoc) {

        /*
         * When loading the HTML file from the WoW Armory, I am 
         * supressing warnings because domDocument expects valid 
         * HTML markup. WoW Armory does not return valid markup.
         */
        $dom = new domDocument;
        @$dom->loadHTML($domDoc);
        $dom->preserveWhiteSpace = false;

        return $dom;
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

        $characterCacheFile = $this->CACHEDIR.$this->character.'.html';
        
        if($this->isCached($characterCacheFile)) {

            $this->characterPage = file_get_contents($characterCacheFile);

        } else { 

            $this->characterPage = file_get_contents($this->charPageURLBuilder());
            file_put_contents($characterCacheFile, $this->characterPage);
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
     * The array is returned. 
     */
    public function getGuildMembers($rank = false) {

        $guildArray = array();

        $guildCacheFile = $this->CACHEDIR.$this->guild.'_roster.html';
  
        if($this->isCached($guildCacheFile)) {
            $guildPage = file_get_contents($guildCacheFile);
        } else {
            $guildPage = file_get_contents($this->guildRosterURLBuilder());
            file_put_contents($guildCacheFile, $guildPage);
        }

        /*
        $dom = new domDocument;
        $dom->loadHTML($guildPage);    
        $dom->preserveWhiteSpace = false;
        */
    
        $dom = $this->loadNewDom($guildPage);

        $roster = $dom->getElementsByTagName('tbody');
        $char = $roster->item(0)->getElementsByTagName('tr');
 
        foreach ($char as $c) {

            $charInfo = $c->getElementsByTagName('td');

            if(!$rank) { 

                array_push($guildArray, $charInfo->item(0)->nodeValue);

            } else {
           
                $guildArray[$charInfo->item(0)->nodeValue] =
                                     substr(trim($charInfo->item(4)->nodeValue), -1);
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

        $perksCacheFile = $this->CACHEDIR.$this->guild.'_perks.html';

        if($this->isCached($perksCacheFile)) {
            $perksPage = file_get_contents($perksCacheFile);
        } else {
            $perksPage = file_get_contents($this->guildPerksURLBuilder());
            file_put_contents($perksCacheFile, $perksPage);
        }

        /*
        $dom = new domDocument;
        $dom->loadHTML($perksPage);    
        $dom->preserveWhiteSpace = false;
        */

        $dom = $this->loadNewDom($perksPage);

        $xpath = new DOMXPath($dom);
       
        //p1, p2, p3,..., pn 
        $index = 1;
        $p = "p";

        $guildLevel = $xpath->query('//span[@class="level"]/strong')->item(0)->nodeValue;

        while($index < $guildLevel) {

            $perk = $xpath->query('//li[@id="'.$p.$index.'"]/div/strong');
            array_push($perksArray, $perk->item(0)->nodeValue);
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

        $contribCacheFile = $this->CACHEDIR.$this->guild.'_contrib.html';

        if($this->isCached($contribCacheFile)) {
            $contribPage = file_get_contents($contribCacheFile);
        } else {
            $contribPage = file_get_contents($this->guildSummaryURLBuilder());
            file_put_contents($contribCacheFile, $contribPage);
        }

        /*
        $dom = new domDocument;
        $dom->loadHTML($contribPage);    
        $dom->preserveWhiteSpace = false;
        */

        $dom = $this->loadNewDom($contribPage);

        $xpath = new DOMXPath($dom);

        $contrib = $xpath->query('//td[@class="name"]/a');
        
        $MAX_CONTRIB = 5;
        $counter = 0;

        while($counter < $MAX_CONTRIB) {

            array_push($contribArray, $contrib->item($counter)->nodeValue);

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

        $guildCacheFile = $this->CACHEDIR.$this->guild.'_roster.html';
  
        if($this->isCached($guildCacheFile)) {
            $guildPage = file_get_contents($guildCacheFile);
        } else {
            $guildPage = file_get_contents($this->guildRosterURLBuilder());
            file_put_contents($guildCacheFile, $guildPage);
        }

        /*
        $dom = new domDocument;
        $dom->loadHTML($guildPage);    
        $dom->preserveWhiteSpace = false;
        */

        $dom = $this->loadNewDom($guildPage);


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

            if(strtolower($charInfo->item(0)->nodeValue) == strtolower($this->character)) {
                return substr($charImages->item(0)->getAttribute('src'),-5,1);
            }

        }

        return null;

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

        
        $statArray = array("name" => $statName->item(0)->nodeValue,
                            "value" => $statValue->item(0)->nodeValue);

        return $statArray;
    }
  
}

?>
