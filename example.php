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

include('./RosterAPI.php');


/*
 * Set the $server, $character, and $guild
 * variables with your information
 */
$server = "Eitrigg";
$guild = "We Know";
$character = "Kastang";

//Creates the RosterAPI Object. 
$api = new RosterAPI($server, $character, $guild);

/*
 * Guild specific information, returns an associative array with
 * guild names and ranks. If you want only guild names, change 
 * (true) to (false) and a normal array will be returned. 
 */
//to filter by level, change getGuildMembers(true) to getGuildMembers(true, LEVEL_HERE)
print_r($api->getGuildMembers(true));

print_r($api->getGuildPerks());
print_r($api->getTopWeeklyContributers());

/*
 * Character specific information
 */
echo $api->getPower().'   ';
echo $api->getClass().'   ';
echo $api->getRace().'   ';
echo $api->getAchievementPoints().'   ';
echo $api->getHealth().'   ';
echo $api->getLevel().'   ';
echo $api->getItemLevel().'   ';
echo $api->getGender().'   ';

//For a complete list of Stat possibilities, 
//please see the getStat function comments 
//in RosterAPI.php
$s = ($api->getStat("spellhaste"));
echo $s["name"].'   ';
echo $s["value"].'   ';

print_r($api->getProfessions());
print_r($api->getTalents());

print_r($api->getItems());


/*
 * The Item Array acts a little differently
 * then the other arrays because it stores
 * an Array of Arrays for Gems. 
 */
$itemArray = $api->getItems();

/* 
 * To Retreive A Gem Name:
 * $itemArray[ITEM_INDEX]["gems"][GEM_INDEX];
 *
 * To Retreive A Name, Enchant, Level of an 
 * Item is much more straight forward:
 * $itemArray[ITEM_INDEX]["name OR level OR enchant"];
 */

/*
 * Please read the documentation in RosterAPI above the
 * function to learn how to properly use this method. 
 */
echo $api->getStatistic(130, "Beverages consumed");

print_r($api->getAllStatNames(130));

?>
