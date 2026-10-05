// @ts-nocheck Material Tailwind 2.1.2 prop types conflict with this project's React 18 types.
"use client";

import React from "react";
import { ThemeProvider } from "@material-tailwind/react";

export function Layout({ children }: { children: React.ReactNode }) {
  return <ThemeProvider>{children}</ThemeProvider>;
}

export default Layout;
