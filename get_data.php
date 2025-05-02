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
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// Initialize response array
$response = [
    "users" => [],
    "posts" => [],
    "comments" => [],
    "likes" => [],
    "shares" => [],
    "followers" => [],
    "hashtags" => []
];

// Get users data
$sql = "SELECT user_id, name, joined_date FROM users ORDER BY user_id DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $response["users"][] = $row;
    }
}

// Get posts data
$sql = "SELECT p.post_id, u.name as user_name, LEFT(p.content, 50) as content, 
        CASE WHEN p.image != '' THEN CONCAT('<img src=\"', p.image, '\" width=\"50\" height=\"50\">') ELSE 'No image' END AS image, 
        p.created_at 
        FROM posts p 
        JOIN users u ON p.user_id = u.user_id 
        ORDER BY p.post_id DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $response["posts"][] = $row;
    }
}

// Get comments data
$sql = "SELECT c.comment_id, u.name as user_name, p.post_id, 
        LEFT(p.content, 30) as post_preview, 
        LEFT(c.comment_text, 50) as comment_text, c.commented_at 
        FROM comments c 
        JOIN users u ON c.user_id = u.user_id 
        JOIN posts p ON c.post_id = p.post_id 
        ORDER BY c.comment_id DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $response["comments"][] = $row;
    }
}

// Get likes data
$sql = "SELECT l.like_id, u.name as user_name, p.post_id, 
        LEFT(p.content, 30) as post_preview, l.liked_at 
        FROM likes l
        JOIN users u ON l.user_id = u.user_id 
        JOIN posts p ON l.post_id = p.post_id 
        ORDER BY l.like_id DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $response["likes"][] = $row;
    }
}

// Get shares data
$sql = "SELECT s.share_id, u.name as user_name, p.post_id, 
        LEFT(p.content, 30) as post_preview, 
        CASE WHEN s.image != '' THEN CONCAT('<img src=\"', s.image, '\" width=\"50\" height=\"50\">') ELSE 'No image' END AS image,
        s.shared_at 
        FROM shares s
        JOIN users u ON s.user_id = u.user_id 
        JOIN posts p ON s.post_id = p.post_id 
        ORDER BY s.share_id DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $response["shares"][] = $row;
    }
}

// Get followers data
$sql = "SELECT f.follower_id, u1.name as follower_name, u2.name as followed_name, f.followed_at 
        FROM followers f
        JOIN users u1 ON f.follower_user = u1.user_id 
        JOIN users u2 ON f.followed_user = u2.user_id 
        ORDER BY f.follower_id DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $response["followers"][] = $row;
    }
}

// Get hashtags data
$sql = "SELECT h.hashtag_id, h.hashtag, p.post_id, LEFT(p.content, 30) as post_preview
        FROM hashtags h
        JOIN posts p ON h.post_id = p.post_id 
        ORDER BY h.hashtag_id DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $response["hashtags"][] = $row;
    }
}

// Return data as JSON
header('Content-Type: application/json');
echo json_encode($response);

$conn->close();
?>