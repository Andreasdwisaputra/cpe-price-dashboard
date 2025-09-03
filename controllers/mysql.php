<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cpe_price_dashboard";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Include ProductModel
include 'models/ProductModel.php';
$productModel = new ProductModel($conn);

// Fetch all products
$products = $productModel->getAllProducts();

// Example: Add a new product
$productModel->addProduct('New Product', 100000, 20); // Nett Price = 100,000 and Margin = 20%
?>
