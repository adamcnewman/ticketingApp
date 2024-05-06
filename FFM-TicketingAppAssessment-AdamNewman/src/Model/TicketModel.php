<?php
/**
 * TicketModel.php
 * 
 */

require_once __DIR__ . "/../Core/Database.php";

class TicketModel {
    private $db;
    private $ticketID;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Creates a new ticket entry in the database
     */
    private function insertTicketEntry($descriptionOfWork) {
        try {
            if (empty($descriptionOfWork)) {
                throw new Exception("Description of work is required");
            }
            $descriptionOfWork = trim($descriptionOfWork);

            $query = 
                "INSERT INTO 
                    ticket (
                        description_of_work
                    ) 
                VALUES 
                    (?)";

            $stmt = $this->db->prepare($query);
            if ($stmt) {
                $stmt->bind_param("s", $descriptionOfWork);
                $success = $stmt->execute();
                if (!$success) {
                    throw new Exception("Query failed: " . $stmt->error);
                }
                $ticketID = $stmt->insert_id;
                $stmt->close();
            }
            $this->ticketID = $ticketID;
        } catch (Exception $e) {
            throw new Exception("Error (insertTicketEntry): " . $e->getMessage());
        }
    }

    /**
     * Creates a new project entry in the database.
     */
    private function insertProjectEntry($projectData) {
        try {
            if (empty($projectData)) {
                throw new Exception("Project data is required");
            }

            $query = 
                "INSERT INTO 
                    project (
                        ticket_id, 
                        customer_id, 
                        job_id, 
                        location_id, 
                        project_status, 
                        ordered_by, 
                        area, 
                        project_date
                    ) 
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($query);
            if ($stmt) {
                $stmt->bind_param("iiiissss", 
                    $this->ticketID,
                    $projectData["customerID"], 
                    $projectData["jobID"], 
                    $projectData["locationID"], 
                    $projectData["status"],
                    $projectData["orderedBy"],
                    $projectData["area"],
                    $projectData["date"]
                );
                $success = $stmt->execute();
                if (!$success) {
                    throw new Exception("Query failed: " . $stmt->error);
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            throw new Exception("Error (insertProjectEntry): " . $e->getMessage());
        }
    }

    /**
     * Batch inserts labour line items into the database.
     * If no labour line items were sent, then $labourLineItems will be an array with a single key "empty" set to true.
     */
    private function insertLabourLineItems($labourLineItems) {
        try {
            if (!(isset($labourLineItems[0]["empty"]) && $labourLineItems[0]["empty"] == true)) {
                foreach ($labourLineItems as $labourLineItem) {
                    $query = 
                        "INSERT INTO 
                            labour_item (
                                ticket_id, 
                                position_id, 
                                uom, 
                                regular_rate, 
                                regular_hours, 
                                overtime_rate, 
                                overtime_hours
                            ) 
                        VALUES 
                            (?, ?, ?, ?, ?, ?, ?)";

                    $stmt = $this->db->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param("iisdddd", 
                            $this->ticketID,
                            $labourLineItem["positionID"], 
                            $labourLineItem["uom"], 
                            $labourLineItem["regularRate"], 
                            $labourLineItem["regularHours"], 
                            $labourLineItem["overtimeRate"], 
                            $labourLineItem["overtimeHours"]
                        );
                        $success = $stmt->execute();
                        if (!$success) {
                            throw new Exception("Query failed: " . $stmt->error);
                        }
                        $stmt->close();
                    }
                }
            }
        } catch (Exception $e) {
            throw new Exception("Error (insertLabourLineItems): " . $e->getMessage());
        }
    }

    /**
     * Batch inserts truck line items into the database.
     * If no truck line items were sent, then $truckLineItems will be an array with a single key "empty" set to true.
     */
    private function insertTruckLineItems($truckLineItems) {
        try {
            if (!(isset($truckLineItems[0]["empty"]) && $truckLineItems[0]["empty"] == true)) {
                foreach ($truckLineItems as $truckLineItem) {
                    $query = 
                        "INSERT INTO 
                            truck_item (
                                ticket_id, 
                                truck_id, 
                                quantity, 
                                uom, 
                                rate, 
                                total
                            ) 
                        VALUES 
                            (?, ?, ?, ?, ?, ?)";

                    $stmt = $this->db->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param("iiisdd", 
                            $this->ticketID,
                            $truckLineItem["truckID"], 
                            $truckLineItem["quantity"], 
                            $truckLineItem["uom"], 
                            $truckLineItem["rate"], 
                            $truckLineItem["total"]
                        );
                        $success = $stmt->execute();
                        if (!$success) {
                            throw new Exception("Query failed: " . $stmt->error);
                        }
                        $stmt->close();
                    }
                }
        }
        } catch (Exception $e) {
            throw new Exception("Error (insertTruckLineItems): " . $e->getMessage());
        }
    }

    /**
     * Batch inserts miscellaneous line items into the database.
     * If no miscellaneous line items were sent, then $miscLineItems will be an array with a single key "empty" set to true.
     */
    private function insertMiscLineItems($miscLineItems) {
        try { 
            if (!(isset($miscLineItems[0]["empty"]) && $miscLineItems[0]["empty"] == true)) {
                foreach ($miscLineItems as $miscLineItem) {
                    $query = 
                        "INSERT INTO 
                            misc_item (
                                ticket_id, 
                                misc_description, 
                                cost, price, 
                                quantity, 
                                total
                            ) 
                        VALUES 
                            (?, ?, ?, ?, ?, ?)";

                    $stmt = $this->db->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param("isdddd", 
                            $this->ticketID,
                            $miscLineItem["description"], 
                            $miscLineItem["cost"], 
                            $miscLineItem["price"], 
                            $miscLineItem["quantity"], 
                            $miscLineItem["total"]
                        );
                        $success = $stmt->execute();
                        if (!$success) {
                            throw new Exception("Query failed: " . $stmt->error);
                        }
                        $stmt->close();
                    }
                }
            }
        } catch (Exception $e) {
            throw new Exception("Error (insertMiscLineItems): " . $e->getMessage());
        }
    }

    /**
     * Creates a transaction to enter a new ticket into the database, and related data in other tables. 
     */
    public function createTicket($descriptionOfWork, $projectData, $labourLineItems, $truckLineItems, $miscLineItems) {
        try {
            $this->db->begin_transaction();
            error_log("Attempting to insert ticket with description: $descriptionOfWork");
            $this->insertTicketEntry($descriptionOfWork);
            error_log("Ticket inserted with ID: " . $this->ticketID);

            error_log("Attempting to insert project with data: " . print_r($projectData, true));
            $this->insertProjectEntry($projectData);
            error_log("Project insertion attempted.");
        
            error_log("Attempting to insert labour with data: " . print_r($labourLineItems, true));
            $this->insertLabourLineItems($labourLineItems);
            error_log("Labour insertion attempted.");

            error_log("Attempting to insert truck with data: " . print_r($truckLineItems, true));
            $this->insertTruckLineItems($truckLineItems);
            error_log("Truck insertion attempted.");

            error_log("Attempting to insert misc with data: " . print_r($miscLineItems, true));
            $this->insertMiscLineItems($miscLineItems);
            error_log("Misc insertion attempted.");

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Rolling Back Transaction" . $e->getMessage());
            throw new Exception("Error (createTicket)" . $e);
        }
    }
}
?>