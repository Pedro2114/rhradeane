<?php
return ['name'=>'Atendimentos, projetos, formações e planejamento com conferência','up'=>function(PDO $db,string $driver):void {
    $id=$driver==='mysql'?'INTEGER PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $suffix=$driver==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    foreach([
        "CREATE TABLE IF NOT EXISTS institutional_access(user_id INTEGER NOT NULL,kind VARCHAR(30) NOT NULL,granted_by INTEGER NOT NULL,created VARCHAR(30) NOT NULL,PRIMARY KEY(user_id,kind),FOREIGN KEY(user_id) REFERENCES users(id))",
        "CREATE TABLE IF NOT EXISTS institutional_records(id $id,kind VARCHAR(30) NOT NULL,title VARCHAR(180) NOT NULL,event_date VARCHAR(10) NOT NULL,due VARCHAR(10) NULL,owner_id INTEGER NOT NULL,creator_id INTEGER NOT NULL,status VARCHAR(40) NOT NULL,payload TEXT NOT NULL,review TEXT NOT NULL,reviewer_id INTEGER NULL,revision INTEGER NOT NULL DEFAULT 1,created VARCHAR(30) NOT NULL,updated VARCHAR(30) NOT NULL,FOREIGN KEY(owner_id) REFERENCES users(id),FOREIGN KEY(creator_id) REFERENCES users(id),FOREIGN KEY(reviewer_id) REFERENCES users(id))",
        "CREATE TABLE IF NOT EXISTS institutional_members(record_id INTEGER NOT NULL,employee_id INTEGER NOT NULL,result VARCHAR(40) NOT NULL,delivered VARCHAR(10) NULL,evidence TEXT NOT NULL,PRIMARY KEY(record_id,employee_id),FOREIGN KEY(record_id) REFERENCES institutional_records(id),FOREIGN KEY(employee_id) REFERENCES employees(id))",
        "CREATE TABLE IF NOT EXISTS institutional_files(id $id,record_id INTEGER NOT NULL,actor_id INTEGER NOT NULL,original_name VARCHAR(180) NOT NULL,stored_name VARCHAR(80) NOT NULL,mime VARCHAR(80) NOT NULL,created VARCHAR(30) NOT NULL,FOREIGN KEY(record_id) REFERENCES institutional_records(id),FOREIGN KEY(actor_id) REFERENCES users(id))",
        "CREATE TABLE IF NOT EXISTS institutional_events(id $id,record_id INTEGER NOT NULL,actor_id INTEGER NOT NULL,action VARCHAR(60) NOT NULL,revision INTEGER NOT NULL,detail TEXT NOT NULL,created VARCHAR(30) NOT NULL,FOREIGN KEY(record_id) REFERENCES institutional_records(id),FOREIGN KEY(actor_id) REFERENCES users(id))"
    ] as $sql)$db->exec($sql.$suffix);
}];
