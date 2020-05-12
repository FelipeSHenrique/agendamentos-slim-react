<?php

use Ramsey\Uuid\Uuid;

function getUserID()
{
    return null;
}

function saveAppointment(\PDO $db,
                         $userID,
                         $name,
                         $address,
                         $landlinePhoneNumber,
                         $mobilePhoneNumber,
                         $email,
                         $numberOfEmployees,
                         $date,
                         $returnDate,
                         $dueDate,
                         $observations)
{
    $stmt = $db->prepare("INSERT INTO appointments VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($date) {
        $dateTime = new \DateTime($date);
        $date = date_format($dateTime, "Y-m-d");
    }

    if ($returnDate) {
        $dateTime = new \DateTime($returnDate);
        $returnDate = date_format($dateTime, "Y-m-d");
    }

    if ($dueDate) {
        $dateTime = new \DateTime($dueDate);
        $dueDate = date_format($dateTime, "Y-m-d");
    }

    $stmt->execute([
        null,
        $userID,
        $name,
        $address,
        $landlinePhoneNumber,
        $mobilePhoneNumber,
        $email,
        $numberOfEmployees,
        $date,
        $returnDate,
        $dueDate,
        $observations
    ]);
    return $db->lastInsertId();
}

function saveAppointmentFile(\PDO $db, $appointmentID, $name, $path)
{
    $stmt = $db->prepare("INSERT INTO appointment_files VALUES(?, ?, ?, ?, ?)");
    $stmt->execute([
        null,
        $appointmentID,
        Uuid::uuid4(),
        $name,
        $path
    ]);
    return $db->lastInsertId();
}

function getAppointment(\PDO $db, $id)
{
    $stmt = $db->prepare("SELECT * FROM appointments WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $result = $stmt->fetch();

    if ($result) {
        $result['files'] = getAppointmentFilesForAppointment($db, $id);
    }

    return $result;
}

function getAppointments(\PDO $db, $userID, $date, $returnDate, $dueDate)
{
    $conditions = [];
    $parameters = [];

    if ($date) {
        $dateTime = new \DateTime($date);
        $date = date_format($dateTime, "Y-m-d");
    }

    if ($returnDate) {
        $dateTime = new \DateTime($returnDate);
        $returnDate = date_format($dateTime, "Y-m-d");
    }

    if ($dueDate) {
        $dateTime = new \DateTime($dueDate);
        $dueDate = date_format($dateTime, "Y-m-d");
    }

    if ($userID) {
        $conditions[] = 'alianca_user_id = ?';
        $parameters[] = $userID;
    }

    if ($date) {
        $conditions[] = 'date = ?';
        $parameters[] = $date;
    }

    if ($returnDate) {
        $conditions[] = 'return_date = ?';
        $parameters[] = $returnDate;
    }

    if ($dueDate) {
        $conditions[] = 'due_date = ?';
        $parameters[] = $dueDate;
    }

    $query = "SELECT * FROM appointments";

    if ($conditions) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }

    $stmt = $db->prepare($query);
    $stmt->execute($parameters);
    $results = $stmt->fetchAll();

    foreach ($results as &$result) {
        $result['files'] = getAppointmentFilesForAppointment($db, $result['id']);
    }
    unset($result);

    return $results;
}

function getAppointmentFile(\PDO $db, $uuid)
{
    $stmt = $db->prepare("SELECT * FROM appointment_files WHERE uuid = ? LIMIT 1");
    $stmt->execute([$uuid]);
    return $stmt->fetch();
}

function getAppointmentFilesForAppointment(\PDO $db, $appointmentID)
{
    $stmt = $db->prepare("SELECT * FROM appointment_files WHERE appointment_id = ?");
    $stmt->execute([$appointmentID]);
    $files = $stmt->fetchAll();

    foreach ($files as &$file) {
        $file['url'] = getBaseURL() . '/api/download/' . $file['uuid'];
    }
    unset($file);

    return $files;
}

function setupDatabase(\PDO $db)
{
    $db->exec("
    CREATE TABLE IF NOT EXISTS appointments (
        id int(10) unsigned NOT NULL AUTO_INCREMENT,
        alianca_user_id int(10) unsigned DEFAULT NULL,
        name varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        address varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        landline_phone_number varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        mobile_phone_number varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        email varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        number_of_employees varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        date datetime DEFAULT NULL,
        return_date datetime DEFAULT NULL,
        due_date datetime DEFAULT NULL,
        observations text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        PRIMARY KEY (id)
    )");

    $db->exec("
    CREATE TABLE IF NOT EXISTS appointment_files (
        id int(10) unsigned NOT NULL AUTO_INCREMENT,
        appointment_id int(10) unsigned NOT NULL,
        uuid varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        name varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        path varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        PRIMARY KEY (id),
        KEY appointment_files_appointment_id_foreign (appointment_id),
        CONSTRAINT appointment_files_appointment_id_foreign FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE
    )");
}
