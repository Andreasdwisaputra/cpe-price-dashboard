<?php
class ProductModel {

    private $conn;

    // Constructor to initialize the database connection
    public function __construct($db) {
        $this->conn = $db;
    }

    // Fetch all products that meet the required criteria (reviews > 5 and sold > 5)
    public function getAllProducts() {
        $query = "SELECT * FROM ecommerce_data WHERE review_count > 5 AND sold_count > 5";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->get_result();
    }

    // Get the details of a single product by its ID
    public function getProductById($productId) {
        $query = "SELECT * FROM ecommerce_data WHERE data_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Add a new product to the products table
    public function addProduct($productName, $nettPrice, $marginMitra) {
        // Calculate the maximum reasonable price using the margin
        $maxReasonablePrice = $nettPrice * (1 + $marginMitra);

        $query = "INSERT INTO products (product_name, nett_price, margin_mitra, max_reasonable_price)
                  VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sdid", $productName, $nettPrice, $marginMitra, $maxReasonablePrice);

        if ($stmt->execute()) {
            return true;
        } else {
            return false;
        }
    }

    // Update an existing product in the products table
    public function updateProduct($productId, $productName, $nettPrice, $marginMitra) {
        // Calculate the maximum reasonable price using the margin
        $maxReasonablePrice = $nettPrice * (1 + $marginMitra);

        $query = "UPDATE products SET product_name = ?, nett_price = ?, margin_mitra = ?, max_reasonable_price = ?
                  WHERE product_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sdidi", $productName, $nettPrice, $marginMitra, $maxReasonablePrice, $productId);

        if ($stmt->execute()) {
            return true;
        } else {
            return false;
        }
    }

    // Delete a product by its ID
    public function deleteProduct($productId) {
        $query = "DELETE FROM products WHERE product_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $productId);

        if ($stmt->execute()) {
            return true;
        } else {
            return false;
        }
    }

    // Calculate the maximum reasonable price based on the nett price and margin mitra
    public function calculateMaxReasonablePrice($nettPrice, $marginMitra) {
        return $nettPrice * (1 + $marginMitra);
    }
}
?>
