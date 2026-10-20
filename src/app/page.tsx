"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { ArrowRightIcon, ArrowUpRightIcon, MagnifyingGlassIcon, MapPinIcon, SparklesIcon, CodeBracketIcon, ChartBarIcon, MegaphoneIcon, CalculatorIcon, UserGroupIcon, BriefcaseIcon, CheckIcon, DocumentTextIcon } from "@heroicons/react/24/outline";
import { jobs, readIds, savedKey } from "@/lib/jobs";
import { JobCard } from "@/components/job-card";

const categories = [
  { name: "Công nghệ thông tin", short: "Công nghệ & lập trình", icon: CodeBracketIcon, color: "violet" },
  { name: "Marketing - Truyền thông", short: "Marketing & sáng tạo", icon: MegaphoneIcon, color: "peach" },
  { name: "Phân tích dữ liệu", short: "Dữ liệu & phân tích", icon: ChartBarIcon, color: "mint" },
  { name: "Kế toán - Kiểm toán", short: "Kế toán & kiểm toán", icon: CalculatorIcon, color: "blue" },
  { name: "Nhân sự", short: "Nhân sự & con người", icon: UserGroupIcon, color: "pink" },
  { name: "Kinh doanh - Bán hàng", short: "Kinh doanh & bán hàng", icon: BriefcaseIcon, color: "yellow" },
];

export default function HomePage() {
  const [saved, setSaved] = useState<number[]>([]);
  const [tab, setTab] = useState("Tất cả");
  useEffect(() => { setSaved(readIds(savedKey)); }, []);
  function toggleSave(id: number) {
    const current = readIds(savedKey);
    const next = current.includes(id) ? current.filter(item => item !== id) : [...current, id];
    localStorage.setItem(savedKey, JSON.stringify(next)); setSaved(next);
  }
  const featured = jobs.filter(job => tab === "Tất cả" || (tab === "Công nghệ" ? job.category === "Công nghệ thông tin" : tab === "Marketing" ? job.category === "Marketing - Truyền thông" : job.type === "Thực tập")).slice(0, 6);
  return <>
    <section className="hero-section">
      <div className="hero-glow" aria-hidden="true" />
      <div className="site-container hero-grid">
        <div className="hero-copy">
          <span className="eyebrow"><span className="status-dot" />MỘT KHỞI ĐẦU MỚI, MỘT BẠN TỐT HƠN</span>
          <h1>Công việc phù hợp.<br /><span className="gradient-text">Tương lai rộng mở.</span><span className="headline-spark" aria-hidden="true">✳</span></h1>
          <p className="hero-description">Mỗi tài năng đều xứng đáng với một cơ hội tốt.<br className="desktop-break" /> Tìm nơi bạn thuộc về và viết tiếp câu chuyện sự nghiệp.</p>
          <form action="/jobs" className="hero-search">
            <label className="search-keyword"><MagnifyingGlassIcon /><input aria-label="Chức danh, kỹ năng hoặc công ty" name="q" placeholder="Vị trí, kỹ năng hoặc công ty…" /></label>
            <label className="search-location"><MapPinIcon /><select name="location" aria-label="Địa điểm làm việc"><option value="">Địa điểm</option><option>TP. Hồ Chí Minh</option><option>Hà Nội</option><option>Đà Nẵng</option></select></label>
            <button className="button button-primary" type="submit">Tìm việc <ArrowRightIcon /></button>
          </form>
          <div className="popular-search"><span>Tìm kiếm phổ biến:</span>{["Python", "React", "Marketing"].map(term => <Link key={term} href={`/jobs?q=${term}`}>{term}<ArrowUpRightIcon /></Link>)}</div>
          <div className="hero-bottom"><div className="mini-avatars" aria-hidden="true"><span>H</span><span>M</span><span>A</span><span>+</span></div><p><strong>Cơ hội mới bắt đầu từ bạn.</strong><br />Sẵn sàng cho bước tiến tiếp theo?</p><span className="hand-arrow" aria-hidden="true">↗</span></div>
        </div>
        <div className="career-visual" aria-label="Minh họa khám phá cơ hội nghề nghiệp">
          <div className="visual-grid" aria-hidden="true" /><div className="orbit orbit-one" /><div className="orbit orbit-two" />
          <span className="visual-spark spark-one" aria-hidden="true">✦</span><span className="visual-spark spark-two" aria-hidden="true">✳</span>
          <div className="floating-note note-top"><span className="note-icon mint"><CheckIcon /></span><div><strong>Một bước gần hơn</strong><span>đến công việc bạn yêu thích</span></div><span aria-hidden="true">✦</span></div>
          <div className="career-window">
            <div className="window-top"><span className="window-dots"><i /><i /><i /></span><span>YOUR NEXT CHAPTER</span><SparklesIcon /></div>
            <div className="window-content"><div className="window-greeting"><div><p>XIN CHÀO, TÀI NĂNG MỚI</p><h2>Cơ hội dành cho bạn <span>✌</span></h2></div><span className="profile-orb">V</span></div>
              <div className="match-banner"><div><span>KHÁM PHÁ TIỀM NĂNG</span><h3>Đúng kỹ năng.<br />Đúng cơ hội.</h3><Link href="/jobs">Bắt đầu khám phá <ArrowUpRightIcon /></Link></div><div className="match-orb"><SparklesIcon /><span>VIEJOB</span><i /><i /></div></div>
              <div className="preview-label"><span>Công việc nổi bật</span><span className="demo-label">Dữ liệu minh họa</span></div>
              {jobs.slice(0, 2).map(job => <Link className="preview-job" href={`/jobs/${job.id}`} key={job.id}><span className={`company-logo logo-${job.id % 6}`}>{job.logo}</span><div><strong>{job.title}</strong><span>{job.company}</span></div><ArrowUpRightIcon /></Link>)}
              <div className="window-footer"><span><span className="status-dot" />Sẵn sàng cho hành trình mới</span><span>↗</span></div>
            </div>
          </div>
          <div className="floating-note note-bottom"><span className="note-icon violet"><BriefcaseIcon /></span><div><strong>Chọn việc. Chọn tương lai.</strong><span>Hành trình của bạn, lựa chọn của bạn.</span></div></div>
          <div className="floating-pill"><span>✧</span> Make your next move.</div>
        </div>
      </div>
      <div className="site-container hero-stats"><div><strong>{jobs.length}<span>+</span></strong><span>Cơ hội để khám phá</span></div><div><strong>06</strong><span>Lĩnh vực nghề nghiệp</span></div><div><strong>03</strong><span>Thành phố kết nối</span></div><p><SparklesIcon />Một nền tảng.<br /><b>Nhiều hướng đi mới.</b></p></div>
    </section>
    <section className="section-space site-container" id="categories" data-reveal>
      <div className="section-heading"><div><span className="eyebrow">TÌM ĐIỂM BẮT ĐẦU CỦA BẠN</span><h2>Mỗi đam mê, <span>một lối đi.</span></h2><p>Khám phá cơ hội trong lĩnh vực bạn muốn tỏa sáng.</p></div><Link className="text-link" href="/jobs">Tất cả ngành nghề <ArrowUpRightIcon /></Link></div>
      <div className="category-grid">{categories.map(({ name, short, icon: Icon, color }, index) => <Link className={`category-tile ${color}`} href={`/jobs?category=${encodeURIComponent(name)}`} key={name} style={{ "--item-delay": `${index * 50}ms` } as React.CSSProperties}><span className={`category-icon ${color}`}><Icon /></span><ArrowUpRightIcon className="category-arrow" /><h3>{short}</h3><p>{jobs.filter(job => job.category === name).length} việc làm đang chờ bạn</p></Link>)}</div>
    </section>
    <section className="featured-section section-space" id="jobs">
      <div className="site-container" data-reveal><div className="section-heading"><div><span className="eyebrow"><span className="status-dot" />CƠ HỘI ĐÁNG CHÚ Ý</span><h2>Bước tiến mới <span>bắt đầu ở đây.</span></h2><p>Một công việc tốt có thể mở ra cả một hành trình.</p></div><Link href="/jobs" className="text-link">Xem tất cả việc làm <ArrowUpRightIcon /></Link></div>
        <div className="filter-tabs" aria-label="Lọc việc làm nổi bật">{["Tất cả", "Công nghệ", "Marketing", "Thực tập"].map(item => <button key={item} onClick={() => setTab(item)} className={tab === item ? "active" : ""} aria-pressed={tab === item}>{item}{item === "Tất cả" && <span>{jobs.length}</span>}</button>)}</div>
        <div className="job-grid">{featured.map(job => <JobCard key={job.id} job={job} saved={saved.includes(job.id)} onSave={() => toggleSave(job.id)} />)}</div>
        <p className="data-note">Danh sách và điểm phù hợp sử dụng dữ liệu minh họa để bạn trải nghiệm nền tảng.</p>
      </div>
    </section>
    <section className="section-space site-container journey-section" data-reveal>
      <div className="journey-intro"><span className="eyebrow">ÍT THAO TÁC HƠN. NHIỀU CƠ HỘI HƠN.</span><h2>Hành trình mới,<br />chỉ <span>3 bước nhỏ.</span></h2><p>Dành thời gian cho điều quan trọng nhất:<br />tìm ra công việc thực sự phù hợp với bạn.</p><Link href="/profile" className="button button-dark">Tạo dấu ấn của bạn <ArrowUpRightIcon /></Link><div className="journey-doodle" aria-hidden="true">✳ <span>your future<br />starts here.</span></div></div>
      <div className="journey-steps">{[{ icon: DocumentTextIcon, title: "Kể câu chuyện của bạn", text: "Hoàn thiện hồ sơ, thêm kỹ năng và chọn CV để sẵn sàng cho cơ hội tiếp theo.", href: "/profile" }, { icon: MagnifyingGlassIcon, title: "Tìm cơ hội đồng điệu", text: "Khám phá việc làm theo kỹ năng, địa điểm và lĩnh vực bạn quan tâm.", href: "/jobs" }, { icon: ArrowUpRightIcon, title: "Tự tin tiến về phía trước", text: "Ứng tuyển và theo dõi các cơ hội của bạn tại một nơi, thật dễ dàng.", href: "/applications" }].map(({ icon: Icon, title, text, href }, i) => <Link href={href} className="journey-step" key={title}><span className="step-number">0{i + 1}</span><span className="step-icon"><Icon /></span><div><h3>{title}</h3><p>{text}</p></div><ArrowUpRightIcon className="step-arrow" /></Link>)}</div>
    </section>
    <section className="site-container cta-wrap" data-reveal><div className="career-cta"><div className="cta-rings" aria-hidden="true" /><span className="cta-spark" aria-hidden="true">✳</span><div><span className="eyebrow">CHƯƠNG TIẾP THEO LÀ CỦA BẠN</span><h2>Đừng chỉ tìm việc.<br />Hãy tìm nơi bạn <em>tỏa sáng.</em></h2><p>Một bước nhỏ hôm nay. Một sự nghiệp khác biệt ngày mai.</p></div><Link href="/jobs" className="button button-lime">Khám phá cơ hội ngay <ArrowUpRightIcon /></Link></div></section>
  </>;
}
