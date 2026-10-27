<?php
return ['name'=>'Prévia de importação e índices de frequência', 'up'=>function(PDO $db,string $driver): void {
    $jsonType=$driver==='mysql'?'LONGTEXT':'TEXT';
    $db->exec("CREATE TABLE IF NOT EXISTS import_previews(token_hash VARCHAR(64) PRIMARY KEY,user_id INTEGER NOT NULL,rows_json $jsonType NOT NULL,expires BIGINT NOT NULL)".($driver==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':''));
    // Marcadores por nome permitem retomar uma migration interrompida sem repetir índices.
    foreach(['idx_punches_person_at'=>'punches(employee_id,at)','idx_leaves_period'=>'leaves(employee_id,start,end)','idx_audit_date'=>'audit(at)'] as $name=>$target){
        $exists=$driver==='sqlite'?$db->query("SELECT name FROM sqlite_master WHERE type='index' AND name='$name'")->fetch():$db->query("SELECT INDEX_NAME FROM information_schema.statistics WHERE table_schema=DATABASE() AND INDEX_NAME='$name'")->fetch();
        if(!$exists)$db->exec("CREATE INDEX $name ON $target");
    }
}];
