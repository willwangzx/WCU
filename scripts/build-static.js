const fs = require("fs");
const path = require("path");

const repoRoot = path.resolve(__dirname, "..");
const distRoot = path.join(repoRoot, "dist");
const topLevelEntries = ["index.html", "assets", "pages"];

fs.rmSync(distRoot, { recursive: true, force: true });
fs.mkdirSync(distRoot, { recursive: true });

for (const entry of topLevelEntries) {
  copyEntry(path.join(repoRoot, entry), path.join(distRoot, entry));
}

function copyEntry(sourcePath, targetPath) {
  const stats = fs.statSync(sourcePath);

  if (stats.isDirectory()) {
    fs.mkdirSync(targetPath, { recursive: true });

    for (const childName of fs.readdirSync(sourcePath)) {
      const childSource = path.join(sourcePath, childName);
      const childTarget = path.join(targetPath, childName);

      if (shouldSkip(childSource)) {
        continue;
      }

      copyEntry(childSource, childTarget);
    }

    return;
  }

  fs.copyFileSync(sourcePath, targetPath);
}

function shouldSkip(sourcePath) {
  const relativePath = path.relative(repoRoot, sourcePath).replace(/\\/g, "/");

  if (relativePath === "") {
    return false;
  }

  if (relativePath.startsWith("pages/") && path.extname(sourcePath) === ".php") {
    return true;
  }

  return false;
}
