// @ts-nocheck Material Tailwind 2.1.2 prop types conflict with this project's React 18 types.
import { Typography, Button, Input } from "@material-tailwind/react";

const LINKS = [
  {
    title: "VIEJOB",
    items: ["Về chúng tôi", "Cơ hội nghề nghiệp", "Cẩm nang", "Liên hệ"],
  },
  {
    title: "Ứng viên",
    items: ["Đăng nhập", "Đăng ký", "Tìm việc", "Hồ sơ cá nhân"],
  },
  {
    title: "Thông tin",
    items: ["Điều khoản", "Bảo mật", "Nhà tuyển dụng", "Trợ giúp"],
  },
];

const CURRENT_YEAR = new Date().getFullYear();

export function Footer() {
  return (
    <footer className="px-8 pt-24 pb-8">
      <div className="container max-w-6xl flex flex-col mx-auto">
        <div className="grid grid-cols-1 lg:grid-cols-3 !w-full ">
          <div className="flex col-span-2 items-center gap-10 mb-10 lg:mb-0 md:gap-36">
            {LINKS.map(({ title, items }) => (
              <ul key={title}>
                <Typography variant="h6" color="blue-gray" className="mb-4">
                  {title}
                </Typography>
                {items.map((link) => (
                  <li key={link}>
                    <Typography
                      as="a"
                      href="#"
                      className="py-1 font-normal !text-gray-700 transition-colors hover:!text-gray-900"
                    >
                      {link}
                    </Typography>
                  </li>
                ))}
              </ul>
            ))}
          </div>
          <div className="">
            <Typography variant="h6" className="mb-3 text-left">
              Nhận tin việc làm
            </Typography>
            <Typography className="!text-gray-500 font-normal mb-4 text-base">
              Đăng ký email để nhận thông tin cơ hội nghề nghiệp mới nhất.
            </Typography>
            <Typography variant="small" className="font-medium mb-2 text-left">
              Email của bạn
            </Typography>
            <div className="flex mb-3 flex-col lg:flex-row items-start gap-4">
              <div className="w-full">
                {/* @ts-ignore */}
                <Input label="Email" color="gray" />
                <Typography className="font-medium mt-3 !text-sm !text-gray-500 text-left">
                  Tôi đồng ý với{" "}
                  <a
                    href="#"
                    className="font-bold underline hover:text-gray-900 transition-colors"
                  >
                    Điều khoản sử dụng{" "}
                  </a>
                </Typography>
              </div>
              <Button color="gray" className="w-full lg:w-fit" size="md">
                Đăng ký
              </Button>
            </div>
          </div>
        </div>
        <Typography
          color="blue-gray"
          className="md:text-center mt-16 font-normal !text-gray-700"
        >
          &copy; {CURRENT_YEAR} VIEJOB. Kết nối tài năng với cơ hội nghề nghiệp.
        </Typography>
      </div>
    </footer>
  );
}

export default Footer;
