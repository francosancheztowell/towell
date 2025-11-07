<div id="globalLoader" class="fixed inset-0 z-50 bg-black/40 hidden">
    <div class="loader absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"></div>
</div>

<style>
    .loader {
        width: 50px;
        height: 50px;
        border: 4px solid #fff;
        border-top: 4px solid #2563eb;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: translate(-50%, -50%) rotate(0deg); }
        100% { transform: translate(-50%, -50%) rotate(360deg); }
    }

    #globalLoader.hidden {
        display: none;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const loader = document.getElementById('globalLoader');
        if (loader) {
            loader.classList.add('hidden');
        }
    });
</script>

