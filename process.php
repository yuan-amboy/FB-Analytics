<?php
// Database connection
$servername = "localhost";
$username = "root"; // Replace with your database username
$password = ""; // Replace with your database password
$dbname = "facebook_analytics_db"; // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process form submission based on form_type
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $form_type = $_POST['form_type'];
    
    switch ($form_type) {
        case 'users':
            addUser($conn);
            break;
        case 'posts':
            addPost($conn);
            break;
        case 'comments':
            addComment($conn);
            break;
        case 'likes':
            addLike($conn);
            break;
        case 'shares':
            addShare($conn);
            break;
        case 'followers':
            addFollower($conn);
            break;
        case 'hashtags':
            addHashtag($conn);
            break;
        default:
            header("Location: index.html?error=invalid_form");
            exit();
    }
    
    // Redirect back to the form with success message
    header("Location: index.html?success=" . $form_type);
    exit();
}

// Functions to handle form submissions

function addUser($conn) {
    $name = sanitizeInput($conn, $_POST['name']);
    $email = sanitizeInput($conn, $_POST['email']);
    $joined_date = sanitizeInput($conn, $_POST['joined_date']);
    
    $sql = "INSERT INTO users (name, email, joined_date) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $name, $email, $joined_date);
    
    if (!$stmt->execute()) {
        header("Location: index.html?error=users&message=" . urlencode($stmt->error));
        exit();
    }
    
    $stmt->close();
}

function addPost($conn) {
    $user_id = sanitizeInput($conn, $_POST['user_id']);
    $content = sanitizeInput($conn, $_POST['content']);
    $created_at = sanitizeInput($conn, $_POST['created_at']);
    $image = ""; // Default empty string
    
    // Handle file upload if present
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "uploads/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $image_name = basename($_FILES["image"]["name"]);
        $target_file = $target_dir . time() . "_" . $image_name;
        
        // Move uploaded file
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image = $target_file;
        }
    }
    
    $sql = "INSERT INTO posts (user_id, content, image, created_at) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $user_id, $content, $image, $created_at);
    
    if (!$stmt->execute()) {
        header("Location: index.html?error=posts&message=" . urlencode($stmt->error));
        exit();
    }
    
    $stmt->close();
}

function addComment($conn) {
    $post_id = sanitizeInput($conn, $_POST['post_id']);
    $user_id = sanitizeInput($conn, $_POST['user_id']);
    $comment_text = sanitizeInput($conn, $_POST['comment_text']);
    $commented_at = sanitizeInput($conn, $_POST['commented_at']);
    
    $sql = "INSERT INTO comments (post_id, user_id, comment_text, commented_at) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $post_id, $user_id, $comment_text, $commented_at);
    
    if (!$stmt->execute()) {
        header("Location: index.html?error=comments&message=" . urlencode($stmt->error));
        exit();
    }
    
    $stmt->close();
}

function addLike($conn) {
    $post_id = sanitizeInput($conn, $_POST['post_id']);
    $user_id = sanitizeInput($conn, $_POST['user_id']);
    $liked_at = sanitizeInput($conn, $_POST['liked_at']);
    
    $sql = "INSERT INTO likes (post_id, user_id, liked_at) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $post_id, $user_id, $liked_at);
    
    if (!$stmt->execute()) {
        header("Location: index.html?error=likes&message=" . urlencode($stmt->error));
        exit();
    }
    
    $stmt->close();
}

function addShare($conn) {
    $post_id = sanitizeInput($conn, $_POST['post_id']);
    $user_id = sanitizeInput($conn, $_POST['user_id']);
    $shared_at = sanitizeInput($conn, $_POST['shared_at']);
    $image = ""; // Default empty string
    
    // Handle file upload if present
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "uploads/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $image_name = basename($_FILES["image"]["name"]);
        $target_file = $target_dir . time() . "_" . $image_name;
        
        // Move uploaded file
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image = $target_file;
        }
    }
    
    $sql = "INSERT INTO shares (post_id, user_id, image, shared_at) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $post_id, $user_id, $image, $shared_at);
    
    if (!$stmt->execute()) {
        header("Location: index.html?error=shares&message=" . urlencode($stmt->error));
        exit();
    }
    
    $stmt->close();
}

function addFollower($conn) {
    $follower_user = sanitizeInput($conn, $_POST['follower_user']);
    $followed_user = sanitizeInput($conn, $_POST['followed_user']);
    $followed_at = sanitizeInput($conn, $_POST['followed_at']);
    
    // Prevent users from following themselves
    if ($follower_user == $followed_user) {
        header("Location: index.html?error=followers&message=" . urlencode("A user cannot follow themselves"));
        exit();
    }
    
    $sql = "INSERT INTO followers (follower_user, followed_user, followed_at) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $follower_user, $followed_user, $followed_at);
    
    if (!$stmt->execute()) {
        header("Location: index.html?error=followers&message=" . urlencode($stmt->error));
        exit();
    }
    
    $stmt->close();
}

function addHashtag($conn) {
    $post_id = sanitizeInput($conn, $_POST['post_id']);
    $hashtag = sanitizeInput($conn, $_POST['hashtag']);
    
    // Remove # symbol if present
    $hashtag = ltrim($hashtag, '#');
    
    $sql = "INSERT INTO hashtags (post_id, hashtag) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $post_id, $hashtag);
    
    if (!$stmt->execute()) {
        header("Location: index.html?error=hashtags&message=" . urlencode($stmt->error));
        exit();
    }
    
    $stmt->close();
}

// Helper function to sanitize inputs
function sanitizeInput($conn, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

$conn->close();
?>