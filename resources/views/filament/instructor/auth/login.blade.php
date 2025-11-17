<style>
    .google-btn-container:hover {
        filter: brightness(90%);
        --tw-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        --tw-shadow-colored: 0 10px 15px -3px var(--tw-shadow-color), 0 4px 6px -4px var(--tw-shadow-color);
        box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);
    }

    .google-btn-container:hover .google-logo-div {
        filter: brightness(111%);
    }
</style>

<div>
    <div>
        <div class="text-center">
            <div class="text-center">
                <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                    Sign In to AutoRank
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Please sign in with your Google account to utilize all the features of AutoRank.
                </p>
            </div>
            <br>

            <div id="webview-error-container" class="w-full"></div>

            <a href="{{ route('google.redirect') }}"
                id="google-login-button"
                class="google-btn-container inline-flex items-stretch border-2 border-primary-500 h-auto w-auto">
                <div class="bg-white p-2">
                    <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="30" height="30" viewBox="0 0 48 48">
                        <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"></path>
                        <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"></path>
                        <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"></path>
                        <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"></path>
                    </svg>
                </div>
                <span class="google-btn-span flex items-center bg-primary-500 text-white px-6">Sign in with Google</span>
            </a>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const userAgent = navigator.userAgent;
        const isDisallowed = /FBAV|Instagram|wv|WebView|Messenger/i.test(userAgent);

        if (!isDisallowed) {
            return;
        }

        const googleLoginButton = document.getElementById("google-login-button");
        const errorContainer = document.getElementById("webview-error-container");

        if (googleLoginButton && errorContainer) {
            googleLoginButton.style.display = "none";

            const errorMessage = document.createElement("div");

            errorMessage.innerHTML = "<strong class='text-gray-950 dark:text-white'>Please Use Your Phone's Browser</strong><p class='mt-2'>This app (like Messenger or Instagram) has opened a temporary web page. For your security, Google logins are blocked here.</p><p class='mt-2'>To sign in, please copy this site's address and paste it into your phone's main browser (like Safari or Chrome).</p>";
            
            errorMessage.className = "text-sm text-gray-500 dark:text-gray-400 p-4 rounded-lg text-center mt-4 mb-4 border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800";
            
            errorContainer.appendChild(errorMessage);
        }
    });
</script>