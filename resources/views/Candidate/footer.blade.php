<footer class="site-footer">
    <a class="smoothscroll scroll-top" href="#top">
        <span class="icon-keyboard_arrow_up">
        </span>
    </a>
    <div class="container">
        <div class="row mb-5">
            <div class="col-6 col-md-3 mb-4 mb-md-0">
                <h3>
                    Tìm kiếm nổi bật
                </h3>
                <ul class="list-unstyled">
                    <li>
                        <a href="{{ route('jobs', ['q' => 'UI']) }}">
                            Thiết kế web
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('jobs', ['q' => 'UI']) }}">
                            Thiết kế giao diện
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('jobs', ['q' => 'Laravel']) }}">
                            Lập trình web
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('jobs', ['q' => 'Python']) }}">
                            Python
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('jobs', ['q' => 'HTML']) }}">
                            HTML5
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('jobs', ['q' => 'CSS']) }}">
                            CSS3
                        </a>
                    </li>
                </ul>
            </div>
            <div class="col-6 col-md-3 mb-4 mb-md-0">
                <h3>
                    Về VIEJOB AI
                </h3>
                <ul class="list-unstyled">
                    <li>
                        <a href="{{ route('about') }}">
                            Giới thiệu
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('jobs') }}">
                            Cơ hội nghề nghiệp
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('companies') }}">
                            Công ty
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('candidate.resumes') }}">
                            Quản lý CV
                        </a>
                    </li>
                </ul>
            </div>
            <div class="col-6 col-md-3 mb-4 mb-md-0">
                <h3>
                    Hỗ trợ
                </h3>
                <ul class="list-unstyled">
                    <li>
                        <a href="{{ route('about') }}">
                            Hỗ trợ
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('about') }}">
                            Thông tin dữ liệu demo
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('about') }}">
                            Phạm vi trải nghiệm
                        </a>
                    </li>
                </ul>
            </div>
            <div class="col-6 col-md-3 mb-4 mb-md-0">
                <h3>
                    Kết nối với chúng tôi
                </h3>
                <div class="footer-social">
                    <a href="{{ route('jobs') }}">
                        <span class="icon-facebook">
                        </span>
                    </a>
                    <a href="{{ route('jobs') }}">
                        <span class="icon-twitter">
                        </span>
                    </a>
                    <a href="{{ route('jobs') }}">
                        <span class="icon-instagram">
                        </span>
                    </a>
                    <a href="{{ route('jobs') }}">
                        <span class="icon-linkedin">
                        </span>
                    </a>
                </div>
            </div>
        </div>
        <div class="row text-center">
            <div class="col-12">
                <p class="copyright">
                    © {{ date('Y') }} VIEJOB AI. Mẫu giao diện bởi
                    <a href="https://colorlib.com" rel="noopener" target="_blank">Colorlib</a>.
                </p>
            </div>
        </div>
    </div>
</footer>
