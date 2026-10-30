<?php
return ['name'=>'Cronograma compartilhado, entregas e anexos', 'up'=>function(PDO $db,string $driver):void {
    $id=$driver==='mysql'?'INTEGER PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';$suffix=$driver==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    foreach([
        "CREATE TABLE IF NOT EXISTS team_tasks(id $id,title VARCHAR(180) NOT NULL,description TEXT NOT NULL,creator_id INTEGER NOT NULL,assignee_id INTEGER NOT NULL,due VARCHAR(30) NOT NULL,dependency_id INTEGER NULL,status VARCHAR(40) NOT NULL,review TEXT NOT NULL,created VARCHAR(30) NOT NULL,updated VARCHAR(30) NOT NULL,FOREIGN KEY(creator_id) REFERENCES users(id),FOREIGN KEY(assignee_id) REFERENCES users(id),FOREIGN KEY(dependency_id) REFERENCES team_tasks(id))",
        "CREATE TABLE IF NOT EXISTS task_deliveries(id $id,task_id INTEGER NOT NULL,actor_id INTEGER NOT NULL,notes TEXT NOT NULL,original_name VARCHAR(180) NOT NULL,stored_name VARCHAR(80) NOT NULL,mime VARCHAR(80) NOT NULL,created VARCHAR(30) NOT NULL,FOREIGN KEY(task_id) REFERENCES team_tasks(id),FOREIGN KEY(actor_id) REFERENCES users(id))"
    ] as $sql)$db->exec($sql.$suffix);
}];
