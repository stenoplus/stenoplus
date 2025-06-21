<?php
session_start();

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); // Redirect to login page
    exit();
}

// ✅ Database Connection
require 'config.php';

try {
    // Handle Add Category
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
        $category_name = trim($_POST['category_name']);

        if (!empty($category_name)) {
            // ✅ Check if category name already exists (case-insensitive)
            $check_stmt = $conn->prepare("SELECT category_id FROM categories WHERE LOWER(category_name) = LOWER(?)");
            $check_stmt->bind_param("s", $category_name);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                $_SESSION['error'] = "Category name already exists!";
            } else {
                // ✅ Insert new category
                $stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
                $stmt->bind_param("s", $category_name);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Category added successfully!";
                } else {
                    $_SESSION['error'] = "Error adding category. Please try again.";
                }
                $stmt->close();
            }
            $check_stmt->close();
        } else {
            $_SESSION['error'] = "Category name cannot be empty.";
        }
    }

    // Handle Delete Category
    if (isset($_GET['delete'])) {
        $category_id = intval($_GET['delete']);
        $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
        $stmt->bind_param("i", $category_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Category deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting category.";
        }
        $stmt->close();
    }

    // Handle Update Category
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
        $category_id = intval($_POST['category_id']);
        $category_name = trim($_POST['category_name']);

        if (!empty($category_name)) {
            // ✅ Check for duplicate name during update
            $check_stmt = $conn->prepare("SELECT category_id FROM categories WHERE LOWER(category_name) = LOWER(?) AND category_id != ?");
            $check_stmt->bind_param("si", $category_name, $category_id);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                $_SESSION['error'] = "Category name already exists!";
            } else {
                $stmt = $conn->prepare("UPDATE categories SET category_name = ?, updated_at = CURRENT_TIMESTAMP WHERE category_id = ?");
                $stmt->bind_param("si", $category_name, $category_id);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Category updated successfully!";
                } else {
                    $_SESSION['error'] = "Error updating category.";
                }
                $stmt->close();
            }
            $check_stmt->close();
        } else {
            $_SESSION['error'] = "Category name cannot be empty.";
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}

// Redirect to categories page
header("Location: ../manage-categories.php");
exit();
?>
