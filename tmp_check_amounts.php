<?php
 = parse_ini_file('/var/www/html/.env');
 = 'mysql:host='.['DB_HOST'].';dbname='.['DB_DATABASE'].';charset=utf8mb4';
 = new PDO(, ['DB_USERNAME'], ['DB_PASSWORD']);
 = ->query('SELECT id, title, amounts, amounts_unit FROM project_job_assignments ORDER BY id DESC LIMIT 5');
 = ->fetchAll(PDO::FETCH_ASSOC);
if(rows\n
