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
/**
 * This class contains general functions used in the RosterAPI 
 * class and will be used with future Classes. 
 *
 * The $CACHETIME and $CACHEDIR variables in this file is the only value that
 * should be changed. 
 *
 * @author Josh Grochowski josh[at]kastang[dot]com
 *
 */
class Functions {

    /*
     * The $CHAR_PAGE_URL and $GUILD_PAGE_URL variables should not need
     * to be altered if you are pulling from the US WoW Armory.
     *
     * As confirmed by Oldertarl or Lightbringer, this Class will also 
     * work on the European WoW Armory by changing 'US' to 'EU'. 
     */
    private static $CHAR_PAGE_URL = "http://us.battle.net/wow/en/character/";
    private static $GUILD_PAGE_URL = "http://us.battle.net/wow/en/guild/";

    /*
     * The $CACHEDIR variable must be the __ABSOLUTE PATH__ to 
     * a desired cache directory. The directory must have write
     * permissions (chmod 777 the directory). The path should also 
     * contain a trailing slash ('/'). 
     * 
     * Example: /path/to/cache/directory/
     */
    private static $CACHEDIR = '/home/kastang/PHP/cache/';

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
     * (62 seconds * 60 minutes)*5. 
     */
    private static $CACHETIME = 18000;
 
    /**
     * Given a path to a file, this function first checks if
     * the file exists, if it has, it checks to see if it has
     * been modified within the caching time. If both of the
     * above criteras have been met, true is returned. Otherwise, 
     * false is returned to the requesting runction. 
     *
     * An exception will be thrown if the $file variable is empty. 
     */ 
    public static function isCached($file, $item = false) {

        //Verifies the file path is not empty. 
        if(empty($file)) {
            throw new Exception("Empty File Name.");
        }

        //If the object being checked is an item, there is no need to
        //update the item at the end of the cache period. 
        if($item == true) {
            if(file_exists($file)) {
                return true;
            }
        }

        //If the file already exists and it is within the cache window, 
        //return true. Otherwise, return false. 
        if(file_exists($file)) {

            $timeSinceModified = strtotime("now") - filemtime($file);

            if($timeSinceModified > self::$CACHETIME) {
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * This function will return the contents of a requested page. 
     * This function first checks to see if the requesting page is 
     * Cached, if it is, the Cached file will be returned. Otherwise 
     * a new file will be fetched from WoW servers. Occasionally WoW
     * will return an empty page, this function will repeat the request
     * to WoW servers for up to 5 times. 
     */
    public function getPageContents($url, $cacheFile) {

        if(self::isCached($cacheFile)) {

            $content = file_get_contents($cacheFile);

            return $content;

        } else { 

            /*
             * If the Content isn't cached, the file must be pulled from
             * WoW Servers. Occasionally a blank page will be returned, 
             * this function will verify the file has content before returning
             * it to the requesting function. If WoW Servers return an empty
             * file, it will attempt to fetch the proper up to 5 times before
             * giving up. 
             */
            $content = file_get_contents($url);

            $retry = 5;
            while(empty($content) && $retry > 0) {

                $content = file_get_contents($url);
                $retry--;
                sleep(.5);

            } 

            /*
             * By this point, the file either is populated, or has ran 
             * out of tries. If the file is still empty, it is mostlikely
             * an error on Blizzards side. 
             */
            file_put_contents($cacheFile, $content);

            return $content;
        }
    }
    
    /**
     * Given an HTML page, loads the HTML into a domDocument and
     * returns the 'object' to the requesting method. 
     */
    public function loadNewDom($domDoc) {

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
     * Returns the $CACHEDIR path. 
     */
    public function getCacheDir() {
        return self::$CACHEDIR;
    }

    /**
     * Returns the $CHAR_PAGE_URL path
     */
    public function getCharPageURL() {
        return self::$CHAR_PAGE_URL;
    }

    /**
     * Returns the $GUILD_PAGE_URL path
     */
    public function getGuildPageURL() {
        return self::$GUILD_PAGE_URL;
    }
}

?>
