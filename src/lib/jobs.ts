export type Job = {
  id: number; title: string; company: string; location: string; category: string;
  type: string; salary: number; posted: string; age: number; match: number;
  skills: string[]; description: string; logo: string;
};

export const jobs: Job[] = [
  { id: 1, title: "Lập trình viên Backend (Python)", company: "Công ty Công nghệ Nova", location: "TP. Hồ Chí Minh", category: "Công nghệ thông tin", type: "Toàn thời gian", salary: 28, posted: "2 giờ trước", age: 2, match: 96, skills: ["Python", "FastAPI", "PostgreSQL"], description: "Xây dựng và phát triển API, phối hợp cùng đội ngũ sản phẩm để mang đến trải nghiệm tốt nhất cho khách hàng.", logo: "N" },
  { id: 2, title: "Kỹ sư phần mềm Python", company: "Finhay Technology", location: "Hà Nội", category: "Công nghệ thông tin", type: "Toàn thời gian", salary: 25, posted: "5 giờ trước", age: 5, match: 91, skills: ["Python", "Django", "MySQL"], description: "Tham gia phát triển nền tảng tài chính số và thiết kế hệ thống đáng tin cậy.", logo: "F" },
  { id: 3, title: "Chuyên viên phân tích dữ liệu", company: "Momo", location: "TP. Hồ Chí Minh", category: "Phân tích dữ liệu", type: "Toàn thời gian", salary: 20, posted: "1 ngày trước", age: 24, match: 78, skills: ["SQL", "Python", "Power BI"], description: "Phân tích dữ liệu người dùng, xây dựng báo cáo và đề xuất dựa trên dữ liệu.", logo: "M" },
  { id: 4, title: "Lập trình viên Frontend React", company: "VNG Corporation", location: "TP. Hồ Chí Minh", category: "Công nghệ thông tin", type: "Toàn thời gian", salary: 32, posted: "2 ngày trước", age: 48, match: 72, skills: ["React", "TypeScript", "Next.js"], description: "Phát triển giao diện web hiện đại, tối ưu hiệu năng và phối hợp cùng đội ngũ thiết kế.", logo: "V" },
  { id: 5, title: "Thực tập sinh Backend", company: "Tiki", location: "TP. Hồ Chí Minh", category: "Công nghệ thông tin", type: "Thực tập", salary: 7, posted: "3 ngày trước", age: 72, match: 85, skills: ["Python", "Git", "SQL"], description: "Cơ hội học hỏi và tham gia xây dựng tính năng thực tế cùng đội ngũ kỹ sư.", logo: "T" },
  { id: 6, title: "Chuyên viên phân tích kinh doanh", company: "FPT Software", location: "Đà Nẵng", category: "Phân tích dữ liệu", type: "Toàn thời gian", salary: 18, posted: "4 ngày trước", age: 96, match: 69, skills: ["SQL", "Excel", "Phân tích"], description: "Thu thập yêu cầu, phân tích quy trình và kết nối nhu cầu kinh doanh với giải pháp công nghệ.", logo: "F" },
  { id: 7, title: "Kế toán tổng hợp", company: "Viettel Digital", location: "Hà Nội", category: "Kế toán - Kiểm toán", type: "Toàn thời gian", salary: 17, posted: "3 giờ trước", age: 3, match: 75, skills: ["Báo cáo tài chính", "Thuế", "MISA"], description: "Theo dõi nghiệp vụ kế toán, lập báo cáo tài chính định kỳ và phối hợp với các phòng ban trong công tác kiểm soát chi phí.", logo: "V" },
  { id: 8, title: "Kế toán công nợ", company: "Thế Giới Di Động", location: "TP. Hồ Chí Minh", category: "Kế toán - Kiểm toán", type: "Toàn thời gian", salary: 14, posted: "1 ngày trước", age: 26, match: 68, skills: ["Excel", "Đối soát", "ERP"], description: "Quản lý công nợ phải thu, đối chiếu số liệu với đối tác và lập báo cáo công nợ hàng tháng.", logo: "T" },
  { id: 9, title: "Chuyên viên Marketing kỹ thuật số", company: "Shopee Việt Nam", location: "TP. Hồ Chí Minh", category: "Marketing - Truyền thông", type: "Toàn thời gian", salary: 22, posted: "4 giờ trước", age: 4, match: 82, skills: ["Digital Marketing", "Google Ads", "Phân tích"], description: "Lập kế hoạch và triển khai chiến dịch quảng cáo đa kênh, theo dõi hiệu quả và tối ưu chuyển đổi.", logo: "S" },
  { id: 10, title: "Nhân viên Content Marketing", company: "Cocoon Việt Nam", location: "TP. Hồ Chí Minh", category: "Marketing - Truyền thông", type: "Toàn thời gian", salary: 13, posted: "1 ngày trước", age: 28, match: 74, skills: ["Sáng tạo nội dung", "SEO", "Mạng xã hội"], description: "Sáng tạo nội dung cho các kênh truyền thông, phát triển ý tưởng chiến dịch và phối hợp cùng đội ngũ thiết kế.", logo: "C" },
  { id: 11, title: "Chuyên viên tuyển dụng", company: "FPT Corporation", location: "Đà Nẵng", category: "Nhân sự", type: "Toàn thời gian", salary: 16, posted: "2 ngày trước", age: 50, match: 71, skills: ["Tuyển dụng", "Phỏng vấn", "Giao tiếp"], description: "Phụ trách quy trình tuyển dụng từ tiếp nhận nhu cầu đến phỏng vấn, xây dựng trải nghiệm tích cực cho ứng viên.", logo: "F" },
  { id: 12, title: "Chuyên viên kinh doanh B2B", company: "Haravan", location: "Hà Nội", category: "Kinh doanh - Bán hàng", type: "Toàn thời gian", salary: 24, posted: "6 giờ trước", age: 6, match: 70, skills: ["B2B", "Đàm phán", "CRM"], description: "Tìm kiếm và phát triển khách hàng doanh nghiệp, tư vấn giải pháp chuyển đổi số và chăm sóc khách hàng.", logo: "H" },
];

export const savedKey = "viejob-saved";
export const appliedKey = "viejob-applied";
export function readIds(key: string): number[] {
  if (typeof window === "undefined") return [];
  try { return JSON.parse(localStorage.getItem(key) || "[]") as number[]; } catch { return []; }
}
