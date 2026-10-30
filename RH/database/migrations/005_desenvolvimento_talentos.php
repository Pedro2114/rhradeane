<?php
return ['name'=>'Premiações, organização e banco de talentos', 'up'=>function(PDO $db,string $driver):void {
    $id=$driver==='mysql'?'INTEGER PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $suffix=$driver==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    foreach([
        "CREATE TABLE IF NOT EXISTS award_cycles(id $id,title VARCHAR(180) NOT NULL,start VARCHAR(10) NOT NULL,end VARCHAR(10) NOT NULL,methodology TEXT NOT NULL)",
        "CREATE TABLE IF NOT EXISTS award_reviews(id $id,cycle_id INTEGER NOT NULL,employee_id INTEGER NOT NULL,prize VARCHAR(20) NOT NULL,status VARCHAR(40) NOT NULL,checks TEXT NOT NULL,decision TEXT NOT NULL,validated VARCHAR(10) NULL,due VARCHAR(10) NULL,delivered VARCHAR(10) NULL,updated VARCHAR(30) NOT NULL,UNIQUE(cycle_id,employee_id,prize),FOREIGN KEY(cycle_id) REFERENCES award_cycles(id),FOREIGN KEY(employee_id) REFERENCES employees(id))",
        "CREATE TABLE IF NOT EXISTS organization_records(id $id,kind VARCHAR(40) NOT NULL,title VARCHAR(180) NOT NULL,details TEXT NOT NULL,updated VARCHAR(30) NOT NULL)",
        "CREATE TABLE IF NOT EXISTS talent_applications(id $id,name VARCHAR(180) NOT NULL,email VARCHAR(240) NOT NULL,phone VARCHAR(30) NOT NULL,area VARCHAR(180) NOT NULL,city VARCHAR(180) NOT NULL,summary TEXT NOT NULL,original_name VARCHAR(180) NOT NULL,stored_name VARCHAR(80) NOT NULL,status VARCHAR(40) NOT NULL,notes TEXT NOT NULL,consent_version VARCHAR(40) NOT NULL,created VARCHAR(30) NOT NULL)"
    ] as $sql)$db->exec($sql.$suffix);
}];
