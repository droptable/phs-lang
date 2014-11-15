/**
 * Minimal Wrapper for the GUI Shell
 * gcc -o phsc-gui.exe -std=c11 -mwindows phsc-gui.c
 */

#include <stdlib.h>
#include <unistd.h>
#include <windows.h>

int main(void)
{
  char buf[256] = { 0 };
  char cmd[] = "./node_modules/nodewebkit/nodewebkit/nw.exe";
  getcwd(buf, 256);
  execl(cmd, cmd, buf, 0);
  return 0;
}
