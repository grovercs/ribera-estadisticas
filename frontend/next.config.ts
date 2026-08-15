import type { NextConfig } from "next";
import path from "path";

const nextConfig: NextConfig = {
  turbopack: {
    // Pin Turbopack workspace root to this Next.js app directory.
    root: path.resolve("."),
  },
};

export default nextConfig;
