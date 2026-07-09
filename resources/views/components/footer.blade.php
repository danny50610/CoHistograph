<footer class="footer bg-dark">
    <div class="container d-flex align-items-center justify-content-between flex-wrap gap-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                {{ html()->a(route('faq'), '常見問題') }}
            </li>
        </ol>
        <div class="powered-by">
            Powered by {{ html()->a('https://github.com/danny50610/CoHistograph', 'CoHistograph')->target('_blank')->attribute('rel', 'noopener noreferrer') }}
        </div>
    </div>
</footer>
