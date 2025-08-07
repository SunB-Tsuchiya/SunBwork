<?php

/** SQLite3 login plugin
 * @link https://www.adminer.org/plugins/#use
 * @author Jakub Vrana, https://www.vrana.cz/
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
 */
class AdminerLoginSqlite
{
    function login($login, $password)
    {
        // SQLiteの場合は任意のユーザー名/パスワードでログインを許可
        return true;
    }

    function loginForm()
    {
        echo "<table cellspacing='0'>\n";
        echo "<tr><th>System<td>" . html_select("auth[driver]", array("sqlite" => "SQLite 3"), DRIVER) . "\n";
        echo "<tr><th>Server<td><input name='auth[server]' value='" . h(SERVER) . "' title='hostname[:port]' placeholder='/var/www/html/database/database.sqlite'>\n";
        echo "<tr><th>Username<td><input name='auth[username]' id='username' value='" . h($_GET["username"]) . "' placeholder='admin'>\n";
        echo "<tr><th>Password<td><input type='password' name='auth[password]' placeholder='admin123'>\n";
        echo "</table>\n";
        echo "<p><input type='submit' value='Login'>\n";
        echo checkbox("auth[permanent]", 1, $_COOKIE["adminer_permanent"], "Permanent login") . "\n";
    }

    function credentials()
    {
        // SQLiteの場合は認証をスキップ
        return array(SERVER, "", "");
    }
}

return new AdminerLoginSqlite;
