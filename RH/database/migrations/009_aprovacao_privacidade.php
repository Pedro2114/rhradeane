<?php
return ['name'=>'Segregação da aprovação de premiações e privacidade do recrutamento','up'=>function(PDO $pdo,string $driver):void{
    $columns=$driver==='mysql'?$pdo->query('SHOW COLUMNS FROM award_reviews')->fetchAll(PDO::FETCH_ASSOC):$pdo->query('PRAGMA table_info(award_reviews)')->fetchAll(PDO::FETCH_ASSOC);
    $names=array_column($columns,$driver==='mysql'?'Field':'name');
    foreach(['created_by','reviewer_id'] as $name)if(!in_array($name,$names,true))$pdo->exec('ALTER TABLE award_reviews ADD COLUMN '.$name.' INTEGER NULL');
    $columns=$driver==='mysql'?$pdo->query('SHOW COLUMNS FROM talent_applications')->fetchAll(PDO::FETCH_ASSOC):$pdo->query('PRAGMA table_info(talent_applications)')->fetchAll(PDO::FETCH_ASSOC);$names=array_column($columns,$driver==='mysql'?'Field':'name');
    foreach(['privacy_snapshot'=>'TEXT NULL','review_due'=>'VARCHAR(10) NULL'] as $name=>$type)if(!in_array($name,$names,true))$pdo->exec('ALTER TABLE talent_applications ADD COLUMN '.$name.' '.$type);
}];
