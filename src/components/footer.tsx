import Link from "next/link";
import { ArrowUpRightIcon } from "@heroicons/react/24/outline";

export function Footer() {
  return <footer className="site-footer"><div className="site-container">
    <div className="footer-main"><div><Link className="brand" href="/"><span className="brand-symbol">v<span>↗</span></span>viejob<span className="brand-period">.</span></Link><p>Kết nối tiềm năng. Mở lối tương lai.<br />Hành trình sự nghiệp bắt đầu từ bạn.</p></div><div className="footer-links"><h3>Khám phá</h3><Link href="/jobs">Tìm việc làm <ArrowUpRightIcon /></Link><Link href="/#categories">Ngành nghề <ArrowUpRightIcon /></Link></div><div className="footer-links"><h3>Dành cho bạn</h3><Link href="/profile">Hồ sơ & CV <ArrowUpRightIcon /></Link><Link href="/applications">Đơn ứng tuyển <ArrowUpRightIcon /></Link></div><div className="footer-signoff"><span>THE NEXT CHAPTER</span><strong>is yours<span>↗</span></strong></div></div>
    <div className="footer-bottom"><span>© {new Date().getFullYear()} VIEJOB. Cùng bạn bước tiếp.</span><span className="footer-demo"><span className="status-dot" />Phiên bản trải nghiệm · Dữ liệu minh họa</span><a href="#main-content">Về đầu trang ↑</a></div>
  </div></footer>;
}
export default Footer;
