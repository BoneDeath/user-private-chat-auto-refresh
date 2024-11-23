<?php
/**
 * Plugin Name: User Private Chat Auto Refresh
 * Description: User Private Chat Auto Refresh is a WordPress plugin that enables logged-in users to communicate privately with each other through a chat system. The plugin displays a floating button that is only visible to logged-in users. Users can initiate a private conversation via a modal window, and the chat will automatically refresh to display the latest messages without the need to manually refresh the page. This plugin is designed to provide a more interactive and responsive communication experience within the site.
 * Version: 1.0
 * Author: BoneDeath <a href="https://github.com/BoneDeath">Github</a>,<a href="https://www.linkedin.com/in/m-hasanuddin-webdev">LinkedIn</a>, Support me, <a href="https://buymeacoffee.com/mhasannudiz">Buy me a coffe ☕</a>
 * License: GPL2
 */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
wp_enqueue_script('jquery');

// Menghindari akses langsung
if (!defined('ABSPATH')) {
    exit;
}

// Membuat tabel untuk menyimpan chat ketika plugin diaktifkan
function user_private_chat_auto_refresh_create_chat_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_chats'; // Nama tabel (gunakan wp_ sebagai prefix default)

    // SQL untuk membuat tabel chat
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        sender_id bigint(20) NOT NULL,
        sender_name varchar(20),
        receiver_id bigint(20) NOT NULL,
        receiver_name varchar(20),
        is_unread tinyint(1),
        message text NOT NULL,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Menjalankan query untuk membuat tabel
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'user_private_chat_auto_refresh_create_chat_table');

// Menampilkan floating button hanya untuk pengguna yang sudah login
function user_private_chat_auto_refresh_display_logged_in()
{
    // Cek apakah pengguna sudah login
    if (is_user_logged_in()) {

        $currentUser = wp_get_current_user();
        ?>
        <div id="floating-button">
            <a href="#" id="open-chat-box" onclick="toggleChatBox()">
                <button>Hubungi Kami</button>
            </a>
        </div>
        <div id="chat-modal" style="display:none;">
            <div id="chat-box">
                <h4 id="title-user" onClick="openUserList()">Percakapan</h4>
                <div class="search-input-layout">
                    <span class="icon-cari" onClick="openUserList()">&#128269;</span>
                    <input class="search-input" placeholder="Cari" oninput="inputCariUser(this)">
                </div>
                <div id="chat-body">
                    <div id="messages"></div>
                    <div id="sender-list"></div>
                    <div id="user-chat-list"></div>
                </div>
                <div id="chat-send-layout">
                    <input id="chat-message" placeholder="Ketik pesan...">
                    <button id="send-message">Kirim</button>
                </div>
            </div>
        </div>
        <style>
            #floating-button {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 9999;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            }

            #floating-button button {
                background-color: #0073e6;
                color: white;
                border: none;
                padding: 15px 20px;
                font-size: 12pt;
                border-radius: 255px;
                cursor: pointer;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
                transition: background-color 0.3s;
            }

            #floating-button button:hover {
                background-color: #005bb5;
            }

            #chat-modal {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                position: fixed;
                bottom: 100px;
                right: 20px;
                width: 350px;
                height: 440px;
                background-color: #fff;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
                padding: 20px;
                border-radius: 8px;
                z-index: 10000;
                display: none;
            }

            #chat-body {
                display: flex;
                flex-direction: row;
            }

            #chat-box {
                display: flex;
                flex-direction: column;
            }

            #chat-box h3 {
                margin: 0 0 10px;
            }

            #messages {
                position: relative;
                display: flex;
                flex-grow: 1;
                flex-direction: column-reverse;
                overflow-y: auto;
                /* border: 1px solid #ccc; */
                padding: 10px;
                height: 250px;
                font-size: 10pt;
            }

            #chat-message {
                margin: 10px 0px;
                border: 1px solid #ccc;
                padding: 6px 12px;
                min-height: 32px;
                border-radius: 6px;
            }

            #send-message {
                background-color: #0073e6;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 4px;
                cursor: pointer;
            }

            #send-message:hover {
                background-color: #005bb5;
            }

            .status-read {
                font-size: 8pt;
                font-weight: none;
            }

            .chat-box {
                margin-bottom: 6px;
                padding: 10px;
                background: #22aaff;
                color: white;
                display: inline-block;
                width: max-content;
                max-width: 70%;
                border-radius: 15px;
            }

            .chat-box.Anda {
                text-align: right;
                background: #eeeeee;
                color: black;
                align-self: flex-end;
            }

            .empty-chat {
                font-size: 8pt;
                text-align: center;
            }

            #sender-list,
            #user-chat-list {
                min-width: 100px;
                height: 270px;
                overflow-y: scroll;
                flex-grow: 1;
                /* border: solid 1px #ccc; */
            }

            #sender-list .sender-container,
            #user-chat-list .sender-container {
                padding: 6px;
                cursor: pointer;
                display: flex;
                /* border-bottom: 1px solid #ccc; */
            }

            #sender-list .sender-container:nth-lastchild(1),
            #user-chat-list .sender-container:nth-lastchild(1) {
                border-bottom: none;
            }

            .sender-container {
                display: flex;
                flex-direction: row;
                padding: 8px;
                font-size: 9pt;
                /* justify-content: space-between; */
            }

            .sender-left {
                display: flex;
                overflow: hidden;
                white-space: nowrap;
                text-overflow: ellipsis;
                width: 50%;
                flex-direction: column;
                justify-content: center;
            }

            .sender-right {
                width: 50%;
                text-align: end;
            }

            #sender-list div:nth-last-child(1),
            #user-chat-list div:nth-last-child(1) {
                border-bottom: none;
            }

            #sender-list div:hover,
            #user-chat-list div:hover {
                background: #eee;
            }

            .badge {
                color: white;
                padding: 3px 6px;
                font-size: 6pt;
                border-radius: 8px;
            }

            .unread {
                background: rgba(34, 170, 255, 0.5)
            }

            .sender-date {
                font-size: 7pt;
            }

            #chat-send-layout {
                display: flex;
                flex-direction: column;
            }

            .search-input-layout {
                display: flex;
                border-radius: 255px;
                margin-bottom: 6px;
                padding-left: 12px;
                flex-direction: row;
                align-items: center;
                background: #eee;
            }

            .search-input {
                padding: 6px;
                flex-grow: 1;
                border-radius: 255px;
                border: none;
                outline: none;
                background: #eee;
                padding-bottom: 10px;
            }

            .icon-cari {
                cursor: pointer;
            }

            .img-user {
                border-radius: 100%;
                margin-right: 12px;
            }

            #title-user {
                cursor: pointer;
                padding: 6px 12px;
                border-radius: 225px;
                display: inline-block;
                width: fit-content;
            }

            #title-user:hover {
                background: #eee;
            }
        </style>
        <?php
        // JavaScript untuk membuka/menutup chat box dan mengirim pesan
        echo '<script>
                var currentReceiver = 0; // Ganti dengan ID penerima yang sesuai
                let timeoutId;

                openUserList();


                function openUserList(){
                    currentReceiver = 0;
                    jQuery("#title-user").html("Percakapan");
                    jQuery("#messages").hide();
                    jQuery("#sender-list").show();
                    jQuery("#user-chat-list").hide();
                    jQuery("#chat-send-layout").hide();
                    jQuery(".search-input-layout").show();
                    jQuery(".search-input").val("");
                    jQuery(".icon-cari").html("&#128269;");
                }
                function openMessageBox(){
                    jQuery("#messages").show();
                    jQuery("#sender-list").hide();
                    jQuery("#user-chat-list").hide();
                    jQuery("#chat-send-layout").show();
                    jQuery(".search-input-layout").hide();
                }
                function openSearchUser(){
                    jQuery("#messages").hide();
                    jQuery("#sender-list").hide();
                    jQuery("#user-chat-list").show();
                    jQuery("#chat-send-layout").hide();
                    jQuery(".icon-cari").html("&#8592;");
                }
                
                function toggleChatBox() {
                    var modal = document.getElementById("chat-modal");
                    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
                }

                // Fungsi untuk mengirim pesan
                document.getElementById("send-message").addEventListener("click", function() {
                    var message = document.getElementById("chat-message").value;
                    if (message.trim() !== "") {
                        sendMessage(message);
                    }
                });

                // Mengirim pesan melalui AJAX
                function sendMessage(message) {
                    if(currentReceiver==0) return;
                    var data = {
                        action: "send_chat_message",
                        message: message,
                        sender_id: ' . $currentUser->ID . ',
                        sender_name: "' . $currentUser->first_name . ' ' . $currentUser->last_name . '",
                        receiver_id: currentReceiver,
                    };

                    jQuery.post("' . admin_url('admin-ajax.php') . '", data, function(response) {
                        document.getElementById("chat-message").value = ""; // Reset input pesan
                        loadMessages(); // Refresh chat setelah pesan dikirim
                    });
                }

                // Memuat daftar pengirim
                function loadSenders() {
                    var data = {
                        action: "load_chat_senders",
                        user_id: ' . get_current_user_id() . '
                    };

                    return jQuery.get("' . admin_url('admin-ajax.php') . '", data, function(response) {
                        document.getElementById("sender-list").innerHTML = response;
                    });
                }





                // Event listener for input with debounce
                function inputCariUser(e) {
                    const query = e.value;
                    if(query.length<3) return; //batalkan jika kurang dari 3 digit

                    // Clear the previous timeout if there is one
                    clearTimeout(timeoutId);

                    // Set a new timeout to delay the search
                    timeoutId = setTimeout(function () {
                        // Call the search function after the delay (e.g., 500ms)
                        cariUserChat(query);
                    }, 500); // 500ms delay
                };


                
                // Mengambil pesan dari server
                function cariUserChat(key) {
                    openSearchUser();
                    var data = {
                        action: "search_user_chat",
                        nama: key,
                        user_id: ' . get_current_user_id() . '
                    };

                    return jQuery.get("' . admin_url('admin-ajax.php') . '", data, function(response) {
                        document.getElementById("user-chat-list").innerHTML = response;
                    });
                }



                // Mengambil pesan dari server
                function loadMessages() {
                    if(currentReceiver==0) return;
                    var data = {
                        action: "load_chat_messages",
                        user_id: ' . get_current_user_id() . ',
                        receiver_id: currentReceiver
                    };

                    return jQuery.get("' . admin_url('admin-ajax.php') . '", data, function(response) {
                        document.getElementById("messages").innerHTML = response;
                    });
                }

                // Memuat pesan berdasarkan sender yang dipilih
                function loadMessagesBySender(senderId, senderName) {
                    if(currentReceiver==senderId)return; //batalkanjika sudah sama
                    jQuery(".search-input").val("");
                    jQuery("#title-user").html("&#8592; "+senderName);
                    currentReceiver = senderId;
                    loadMessages(); // Refresh chat dengan pengirim yang dipilih
                    openMessageBox();
                }

                function toggleUserList(){
                    jQuery("#sender-list").toggle();
                }

                async function loadChatDataRepeatedly() {
                    while (true) {
                        await loadSenders(); // Wait for the loadSenders request to finish
                        await loadMessages(); // Wait for the loadMessages request to finish
                        await new Promise(resolve => setTimeout(resolve, 5000)); // Wait 5 seconds
                    }
                }

                loadChatDataRepeatedly();
              </script>';
    }
}
add_action('admin_footer', 'user_private_chat_auto_refresh_display_logged_in');
add_action('wp_footer', 'user_private_chat_auto_refresh_display_logged_in');

// Fungsi untuk mengirim pesan chat
function user_private_chat_auto_refresh_send_chat_message()
{
    global $wpdb;
    $sender_id = intval($_POST['sender_id']);
    $sender_name = strval($_POST['sender_name']);
    $receiver_id = intval($_POST['receiver_id']);
    $receiver_name=get_user_meta( $receiver_id, "first_name", true).' '.get_user_meta( $receiver_id, "last_name", true);
    $message = sanitize_text_field($_POST['message']);

    // Masukkan pesan ke database
    $wpdb->insert(
        $wpdb->prefix . 'user_chats',
        array(
            'sender_id' => $sender_id,
            'sender_name' => $sender_name,
            'receiver_id' => $receiver_id,
            'receiver_name'=>$receiver_name,
            'message' => $message,
            'is_unread' => 1
        )
    );

    wp_die(); // Mengakhiri AJAX request
}
add_action('wp_ajax_send_chat_message', 'user_private_chat_auto_refresh_send_chat_message');

// Fungsi untuk memuat pesan chat
function user_private_chat_auto_refresh_load_chat_messages()
{
    global $wpdb;
    $user_id = intval($_GET['user_id']);
    $receiver_id = intval($_GET['receiver_id']);

    // Ambil pesan antar dua pengguna
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}user_chats 
            WHERE (sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d) 
            ORDER BY timestamp DESC limit 20",
            $user_id,
            $receiver_id,
            $receiver_id,
            $user_id
        )
    );

    if ($results) {
        // Tampilkan pesan antar dua pengguna
        foreach ($results as $message) {
            $formatted_time = date('H:i', strtotime($message->timestamp));
            $me = ($user_id == $message->sender_id);
            $sender_name = ($me) ? 'Anda' : $message->sender_name;
            $isread = (($me) ? ($message->is_unread == 0) ? '&#x2705;' : '<span style="filter:grayscale(1)">&#x2705;</font>' : '');
            echo '<div class="chat-box ' . $sender_name . '"><small class="status-read">' . esc_html($sender_name) . '</small><br> ' . esc_html($message->message) . '<br><br><small class="status-read">' . $formatted_time . ' ' . $isread . '</small></div>';
        }


        if ($user_id != $receiver_id) {
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->prefix}user_chats SET is_unread =0 WHERE receiver_id = %d AND sender_id=%d",
                $user_id,
                $receiver_id
            );
            $wpdb->query($sql);
        }


    } else {
        echo '<div class="empty-chat">Belum ada pesan</div>';
    }

    wp_die(); // Mengakhiri AJAX request
}
add_action('wp_ajax_load_chat_messages', 'user_private_chat_auto_refresh_load_chat_messages');

// Fungsi untuk memuat daftar pengirim
function user_private_chat_auto_refresh_load_chat_senders()
{
    global $wpdb;
    $user_id = intval($_GET['user_id']);
    $query = "
        SELECT
            CASE WHEN sender_id = %d THEN CONCAT('Anda: ',message) ELSE message END as pesan_terakhir,
            max(timestamp) as last_message_time,
            CASE WHEN sender_id != %d THEN max(is_unread) END as unread_count,
            CASE WHEN sender_id = %d THEN receiver_id ELSE sender_id END as chat_id,
            CASE WHEN sender_id = %d THEN receiver_name ELSE sender_name END as chat_name
            
        FROM {$wpdb->prefix}user_chats
        WHERE receiver_id = %d OR sender_id= %d
        GROUP BY chat_name
        ORDER BY timestamp DESC
    ";
    // Ambil daftar pengirim
    $results = $wpdb->get_results(
        $wpdb->prepare(
            $query,
            $user_id,
            $user_id,
            $user_id,
            $user_id,
            $user_id,
            $user_id,
        )
    );

    if ($results) {
        foreach ($results as $sender) {
            $formatted_time = date('d-m-y H:i', strtotime($sender->last_message_time));
            $unread = ($sender->unread_count > 0) ? 'unread' : '';


            $avatar_url = get_avatar_url($sender->chat_id, array('size' => 55));

            // Default fallback image URL (if no Gravatar is set)
            $default_avatar = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSAhEOYTOMNLDkzULpt0bj-RdWGvRsfw5S-aQ&s';

            // Check if the author has a Gravatar, otherwise use the default avatar
            if (empty($avatar_url)) {
                $avatar_url = $default_avatar;
            }
            ?>
            <div class="sender-container"
                onclick="loadMessagesBySender('<?= esc_attr($sender->chat_id); ?>','<?= esc_attr($sender->chat_name); ?>')">
                <img class="img-user" src="<?= $avatar_url; ?>" />
                <div class="sender-left">
                    <span>
                        <strong><?=esc_html($sender->chat_name); ?></strong>
                    </span>
                    <span>
                        <?= esc_html($sender->pesan_terakhir); ?>
                    </span>
                </div>
                <div class="sender-right">
                    <span class="sender-date"><?= $formatted_time ?></span>
                    <br>
                    <br>
                    <span class="badge <?= $unread; ?>"><?= esc_html($sender->unread_count); ?></span>
                </div>
            </div>
            <?php
        }
    }


    wp_die(); // Mengakhiri AJAX request
}
add_action('wp_ajax_load_chat_senders', 'user_private_chat_auto_refresh_load_chat_senders');




function user_private_chat_auto_refresh_search_user_chat()
{
    global $wpdb;
    $user_id = intval($_GET['user_id']);
    $nama = strval($_GET['nama']);

    $query = "SELECT ID, display_name FROM {$wpdb->prefix}users WHERE ID != %d AND display_name LIKE '%$nama%' LIMIT 10";
    // Ambil daftar pengirim
    $results = $wpdb->get_results(
        $wpdb->prepare(
            $query,
            $user_id
        )
    );

    if ($results) {
        foreach ($results as $sender) {

            $avatar_url = get_avatar_url($sender->ID, array('size' => 25));
            // Default fallback image URL (if no Gravatar is set)
            $default_avatar = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSAhEOYTOMNLDkzULpt0bj-RdWGvRsfw5S-aQ&s';
            // Check if the author has a Gravatar, otherwise use the default avatar
            if (empty($avatar_url)) {
                $avatar_url = $default_avatar;
            }
            ?>
            <div class="sender-container"
                onclick="loadMessagesBySender('<?= esc_attr($sender->ID); ?>','<?= esc_attr($sender->display_name); ?>')">
                <img class="img-user" src="<?= $avatar_url; ?>" width="25">
                <div class="sender-left">
                    <strong><?= esc_html($sender->display_name); ?></strong>
                </div>
            </div>
            <?php
        }
    } else {
        echo '<div class="empty-chat">User tidak ditemukan</div>';
    }
    wp_die(); // Mengakhiri AJAX request
}
add_action('wp_ajax_search_user_chat', 'user_private_chat_auto_refresh_search_user_chat');
?>