document.getElementById("loginBtn").addEventListener("click", login);

async function login() {

    const username = document.getElementById("username").value.trim();
    const password = document.getElementById("password").value.trim();
    const msg = document.getElementById("msg");

    msg.innerText = "Logging in...";

    try {

        const response = await fetch("../../api/login.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ username, password })
        });

        const data = await response.json();

        if (data.status === "success") {
            msg.style.color = "green";
            msg.innerText = "Login successful. Redirecting...";
            setTimeout(() => {
                window.location.href = "../../dashboard/dashboard.php";
            }, 800);
        } else {
            msg.style.color = "red";
            msg.innerText = data.message;
        }

    } catch (error) {
        msg.innerText = "Server error. Try again.";
    }
}
